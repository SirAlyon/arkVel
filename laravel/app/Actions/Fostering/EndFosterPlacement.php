<?php

declare(strict_types=1);

namespace App\Actions\Fostering;

use App\Actions\Animals\TransitionAnimalStatus;
use App\Enums\AnimalStatus;
use App\Enums\FosterApplicationStatus;
use App\Enums\FosterPlacementStatus;
use App\Exceptions\CannotEndFosterPlacement;
use App\Exceptions\InvalidFosterApplicationTransition;
use App\Models\FosterPlacement;
use App\Models\User;
use App\Notifications\FosterPlacementEnded;
use App\States\Foster\Completed;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\Exceptions\CouldNotPerformTransition;

/**
 * Conclude l'affido fisico: l'animale rientra (o comunque esce dall'affido) e la
 * richiesta di origine, se presente, viene completata.
 *
 * Single responsibility: è l'UNICO punto in cui un FosterPlacement passa a Ended
 * e la richiesta associata raggiunge Completed. Chiude il flusso di affido
 * (Active → Ended / Completed). Tutti i side-effect (chiusura affido, transizione
 * richiesta, rientro dell'animale → Available, notifica) vivono in UNA sola
 * DB::transaction: qualunque fallimento fa rollback dell'intero atto, senza
 * stato/log parziali. Fa da specchio a App\Actions\Adoptions\FinalizeAdoption.
 *
 * Dependency Inversion: la transizione dell'animale passa SEMPRE da
 * TransitionAnimalStatus (guardia + activity log), mai mutando la colonna a mano.
 */
final class EndFosterPlacement
{
    public function __construct(
        private readonly TransitionAnimalStatus $transitionAnimalStatus,
    ) {}

    /**
     * @throws CannotEndFosterPlacement quando l'affido non è in corso (status != Active)
     * @throws InvalidFosterApplicationTransition quando il grafo vieta il passaggio
     *                                            della richiesta a Completed
     */
    public function handle(
        FosterPlacement $placement,
        User $actor,
        ?CarbonInterface $endDate = null,
        ?string $notes = null,
    ): FosterPlacement {
        // Guardia: si conclude SOLO un affido in corso. Fuori da Active è un'azione
        // di dominio non consentita, valutata prima di mutare qualunque stato.
        if ($placement->status !== FosterPlacementStatus::Active) {
            throw CannotEndFosterPlacement::notActive($placement);
        }

        return DB::transaction(function () use ($placement, $actor, $endDate, $notes): FosterPlacement {
            // Concorrenza: serializziamo sull'affido (lockForUpdate) e RILEGGIAMO lo
            // stato dentro la transazione, così due chiusure concorrenti (o una
            // chiusura in gara con un ritiro che chiude lo stesso affido) non
            // superano entrambe la guardia. Postgres onora il lock; SQLite lo ignora
            // senza danni (i test girano serialmente). Specchia il lockForUpdate di
            // StartFosterPlacement sul lato apertura.
            FosterPlacement::query()
                ->whereKey($placement->getKey())
                ->lockForUpdate()
                ->first();

            $placement->refresh();

            if ($placement->status !== FosterPlacementStatus::Active) {
                throw CannotEndFosterPlacement::notActive($placement);
            }

            // 1. Richiesta di origine (se presente) → Completed. Un affido diretto/
            //    di emergenza può non avere una richiesta: in quel caso saltiamo.
            $application = $placement->fosterApplication;

            if ($application !== null) {
                $applicationFrom = $application->statusEnum();

                try {
                    $application->status->transitionTo(Completed::class);
                } catch (CouldNotPerformTransition) {
                    throw InvalidFosterApplicationTransition::between(
                        $applicationFrom,
                        FosterApplicationStatus::Completed,
                    );
                }

                if ($application->decided_at === null) {
                    $application->decided_at = now();
                }
                $application->save();
            }

            // 2. Chiusura dell'affido fisico. Normalizziamo verso Illuminate\Carbon
            //    (tipo della colonna castata) accettando qualunque CarbonInterface.
            $placement->end_date = $endDate !== null ? Carbon::instance($endDate) : now();
            $placement->status = FosterPlacementStatus::Ended;
            if ($notes !== null) {
                $placement->notes = $notes;
            }
            $placement->save();

            // 3. Rientro dell'animale (Fostered → Available) SOLO se l'animale è
            //    effettivamente ancora in affido. Un animale già uscito dall'affido
            //    per altra via (foster-to-adopt → Adopted, fermo sanitario, o già
            //    rilasciato) NON viene toccato: forzarne la transizione violerebbe il
            //    grafo (InvalidAnimalStatusTransition) e farebbe fallire una chiusura
            //    legittima. Specchia la guardia di WithdrawFosterApplication.
            if ($placement->animal->status === AnimalStatus::Fostered) {
                $this->transitionAnimalStatus->handle($placement->animal, AnimalStatus::Available);
            }

            activity()
                ->performedOn($placement)
                ->causedBy($actor)
                ->withProperties([
                    'application_id' => $placement->foster_application_id,
                    'animal_id' => $placement->animal_id,
                ])
                ->log('foster.placement_ended');

            $placement->user->notify(new FosterPlacementEnded($placement));

            return $placement;
        });
    }
}
