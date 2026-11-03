<?php

declare(strict_types=1);

namespace App\Actions\Fostering;

use App\Actions\Animals\TransitionAnimalStatus;
use App\Enums\AnimalStatus;
use App\Enums\FosterApplicationStatus;
use App\Enums\FosterPlacementStatus;
use App\Enums\FosterPlacementType;
use App\Exceptions\AnimalAlreadyInFoster;
use App\Exceptions\InvalidAnimalStatusTransition;
use App\Exceptions\InvalidFosterApplicationTransition;
use App\Models\Animal;
use App\Models\FosterApplication;
use App\Models\FosterPlacement;
use App\Models\User;
use App\Notifications\FosterPlacementStarted;
use App\States\Foster\Active;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\Exceptions\CouldNotPerformTransition;

/**
 * Avvia l'affido fisico a partire da una richiesta APPROVATA: nasce il
 * FosterPlacement e l'animale viene collocato presso l'affidatario.
 *
 * Single responsibility: è l'UNICO punto in cui una richiesta raggiunge Active e
 * nasce un FosterPlacement. Combina il pattern di riserva concorrente di
 * AdvanceApplication (lockForUpdate sull'animale + guardia "un solo affido attivo
 * per animale") con la creazione del record di FinalizeAdoption. Tutti i
 * side-effect (transizione richiesta, creazione affido, transizione animale
 * → Fostered, notifica) vivono in UNA sola DB::transaction: qualunque fallimento
 * (es. animale non collocabile) fa rollback dell'intero atto, senza stato/log
 * parziali.
 *
 * Dependency Inversion: la transizione dell'animale passa SEMPRE da
 * TransitionAnimalStatus (guardia + activity log), mai mutando la colonna a mano.
 *
 * Concorrenza "un solo affido attivo per animale": la guardia
 * assertAnimalNotAlreadyFostered gira DENTRO la transazione, dopo aver preso un
 * lock sulla riga animale (lockForUpdate). Serializzare sull'animale chiude la
 * finestra TOCTOU in cui due richieste sorelle aprirebbero entrambe un affido.
 * Postgres onora il lock; SQLite lo ignora senza danni.
 */
final class StartFosterPlacement
{
    public function __construct(
        private readonly TransitionAnimalStatus $transitionAnimalStatus,
    ) {}

    /**
     * @throws InvalidFosterApplicationTransition quando la richiesta non è
     *                                            Approved, o il grafo vieta il
     *                                            passaggio ad Active
     * @throws AnimalAlreadyInFoster quando l'animale ha già un affido attivo
     * @throws InvalidAnimalStatusTransition quando l'animale non è in uno stato
     *                                       collocabile (propagata, fa rollback)
     */
    public function handle(
        FosterApplication $application,
        User $actor,
        FosterPlacementType $type,
        ?CarbonInterface $startDate = null,
        ?CarbonInterface $endDate = null,
        ?string $notes = null,
    ): FosterPlacement {
        $from = $application->statusEnum();

        // Guardia: si avvia l'affido SOLO da Approved. Fuori da questo stato è una
        // transizione di dominio non consentita, coerente con la superficie
        // d'errore delle altre Action.
        if ($from !== FosterApplicationStatus::Approved) {
            throw InvalidFosterApplicationTransition::between($from, FosterApplicationStatus::Active);
        }

        return DB::transaction(function () use ($application, $actor, $type, $startDate, $endDate, $notes): FosterPlacement {
            // Guardia "un solo affido attivo per animale" resa RACE-SAFE: la
            // eseguiamo DENTRO la transazione e serializziamo sulla riga animale
            // (lockForUpdate) PRIMA di leggere gli affidi concorrenti. Postgres
            // onora il row lock; SQLite lo ignora senza danni (i test girano
            // comunque serialmente).
            Animal::query()
                ->whereKey($application->animal_id)
                ->lockForUpdate()
                ->first();

            $this->assertAnimalNotAlreadyFostered($application);

            // Richiesta Approved → Active (atomica: il grafo la vieta → rollback).
            try {
                $application->status->transitionTo(Active::class);
            } catch (CouldNotPerformTransition) {
                throw InvalidFosterApplicationTransition::between(
                    FosterApplicationStatus::Approved,
                    FosterApplicationStatus::Active,
                );
            }

            $placement = FosterPlacement::query()->create([
                'animal_id' => $application->animal_id,
                'user_id' => $application->user_id,
                'organization_id' => $application->organization_id,
                'foster_application_id' => $application->getKey(),
                'type' => $type,
                'start_date' => $startDate ?? now(),
                'end_date' => $endDate,
                'status' => FosterPlacementStatus::Active,
                'notes' => $notes,
            ]);

            // Animale → Fostered (Available → Fostered è legale). Se l'animale non
            // è collocabile, InvalidAnimalStatusTransition propaga e la transazione
            // fa rollback (nessun affido orfano).
            $this->transitionAnimalStatus->handle($application->animal, AnimalStatus::Fostered);

            activity()
                ->performedOn($placement)
                ->causedBy($actor)
                ->withProperties([
                    'application_id' => $application->getKey(),
                    'animal_id' => $application->animal_id,
                    'type' => $type->value,
                ])
                ->log('foster.placement_started');

            $application->user->notify(new FosterPlacementStarted($placement));

            return $placement;
        });
    }

    /**
     * @throws AnimalAlreadyInFoster
     */
    private function assertAnimalNotAlreadyFostered(FosterApplication $application): void
    {
        /** @var FosterPlacement|null $conflicting */
        $conflicting = FosterPlacement::query()
            ->where('animal_id', $application->animal_id)
            ->where('status', FosterPlacementStatus::Active)
            ->first();

        if ($conflicting !== null) {
            throw AnimalAlreadyInFoster::for($application->animal, $conflicting->getKey());
        }
    }
}
