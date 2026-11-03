<?php

declare(strict_types=1);

namespace App\Actions\Adoptions;

use App\Actions\Animals\TransitionAnimalStatus;
use App\Enums\AnimalStatus;
use App\Enums\ApplicationStatus;
use App\Enums\FollowUpStatus;
use App\Exceptions\InvalidAnimalStatusTransition;
use App\Exceptions\InvalidApplicationTransition;
use App\Models\Adoption;
use App\Models\AdoptionApplication;
use App\Models\User;
use App\Notifications\AdoptionCompleted;
use App\Notifications\ApplicationStatusChanged;
use App\States\Application\Completed;
use App\States\Application\Rejected;
use App\States\Application\Withdrawn;
use App\Support\Contracts\ContractGenerator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\ModelStates\Exceptions\CouldNotPerformTransition;

/**
 * Finalizza un'adozione a partire da una candidatura in AdoptionPending.
 *
 * Single responsibility: è l'UNICO punto in cui una candidatura raggiunge
 * Completed e nasce un'Adoption. Chiude il flusso submitted → completed. Tutti i
 * side-effect (transizione candidatura, creazione adozione, transizione animale
 * → Adopted, chiusura delle candidature sorelle, contratto PDF, notifica) vivono
 * in UNA sola DB::transaction: qualunque fallimento (es. l'animale non è
 * finalizzabile) fa rollback dell'intero atto, senza stato/log parziali.
 *
 * Dependency Inversion: dipende dall'INTERFACCIA ContractGenerator (binding in
 * AppServiceProvider), così i test possono iniettare un fake senza motore PDF.
 * La transizione dell'animale passa SEMPRE da TransitionAnimalStatus (guardia +
 * activity log), mai mutando la colonna a mano.
 */
final class FinalizeAdoption
{
    public function __construct(
        private readonly TransitionAnimalStatus $transitionAnimalStatus,
        private readonly ContractGenerator $contractGenerator,
    ) {}

    /**
     * @throws InvalidApplicationTransition quando la candidatura non è in
     *                                      AdoptionPending, o il grafo vieta il
     *                                      passaggio a Completed
     * @throws InvalidAnimalStatusTransition quando l'animale non è in uno stato
     *                                       finalizzabile (propagata, fa rollback)
     */
    public function handle(
        AdoptionApplication $application,
        User $actor,
        ?float $fee = null,
        string $currency = 'EUR',
    ): Adoption {
        $from = $application->statusEnum();

        // Guardia: si finalizza SOLO da AdoptionPending. Fuori da questo stato è
        // una transizione di dominio non consentita, coerente con la superficie
        // d'errore delle altre Action.
        if ($from !== ApplicationStatus::AdoptionPending) {
            throw InvalidApplicationTransition::between($from, ApplicationStatus::Completed);
        }

        return DB::transaction(function () use ($application, $actor, $fee, $currency): Adoption {
            // 1. Candidatura → Completed (atomica: il grafo la vieta → rollback).
            try {
                $application->status->transitionTo(Completed::class);
            } catch (CouldNotPerformTransition) {
                throw InvalidApplicationTransition::between(
                    ApplicationStatus::AdoptionPending,
                    ApplicationStatus::Completed,
                );
            }

            if ($application->decided_at === null) {
                $application->decided_at = now();
            }
            $application->save();

            // 2. Nasce l'Adozione (parti + data + contributo). Il contratto viene
            //    allegato dopo, quando conosciamo l'id per il nome file.
            $adoption = Adoption::query()->create([
                'animal_id' => $application->animal_id,
                'user_id' => $application->user_id,
                'organization_id' => $application->organization_id,
                'application_id' => $application->getKey(),
                'adopted_at' => now(),
                'fee' => $fee,
                'currency' => $currency,
                'follow_up_status' => FollowUpStatus::Pending,
            ]);

            // 3. Animale → Adopted. L'animale è AdoptionPending dalla riserva
            //    fatta in AdvanceApplication: AdoptionPending → Adopted è legale.
            //    Se non lo fosse, InvalidAnimalStatusTransition propaga e la
            //    transazione fa rollback (nessuna adozione orfana).
            $this->transitionAnimalStatus->handle($application->animal, AnimalStatus::Adopted);

            // 4. Chiude le candidature SORELLE aperte per lo stesso animale.
            $this->rejectSiblingApplications($application);

            // 5. Genera e archivia il contratto PDF sul disco PRIVATO 'local'.
            $contractPath = "contracts/adoption-{$adoption->getKey()}.pdf";
            Storage::disk('local')->put($contractPath, $this->contractGenerator->generate($adoption));
            $adoption->contract_path = $contractPath;
            $adoption->save();

            // 6. Notifica l'adottante (mail con contratto allegato + database) e
            //    logga la finalizzazione (causata dall'attore).
            $application->user->notify(new AdoptionCompleted($adoption));

            activity()
                ->performedOn($adoption)
                ->causedBy($actor)
                ->withProperties([
                    'application_id' => $application->getKey(),
                    'animal_id' => $application->animal_id,
                    'fee' => $fee,
                    'currency' => $currency,
                ])
                ->log('adoption.finalized');

            return $adoption;
        });
    }

    /**
     * Chiude (Rejected, motivazione "animale adottato") ogni ALTRA candidatura
     * dello stesso animale ancora aperta (NON terminale) e notifica ciascun
     * adottante. La transizione avviene inline (senza ripassare per le guardie di
     * AdvanceApplication): il grafo consente Rejected da tutti gli stati aperti
     * pertinenti (UnderReview / Interview) e il fan-out qui è deterministico.
     */
    private function rejectSiblingApplications(AdoptionApplication $application): void
    {
        $reason = __('adoptions.sibling_rejected_reason');

        $siblings = AdoptionApplication::query()
            ->where('animal_id', $application->animal_id)
            ->whereKeyNot($application->getKey())
            ->whereNotState('status', [
                Completed::class,
                Rejected::class,
                Withdrawn::class,
            ])
            ->get();

        foreach ($siblings as $sibling) {
            $siblingFrom = $sibling->statusEnum();

            try {
                $sibling->status->transitionTo(Rejected::class);
            } catch (CouldNotPerformTransition) {
                // Il grafo non consente Rejected da questo stato (es. Approved,
                // AdoptionPending): la guardia "una sola adozione attiva per
                // animale" (AdvanceApplication) impedisce che esistano altre
                // candidature in quegli stati per lo stesso animale, quindi qui
                // non capita. Difesa: saltiamo senza rompere la finalizzazione.
                continue;
            }

            $sibling->decided_at = now();
            $sibling->decision_reason = $reason;
            $sibling->save();

            activity()
                ->performedOn($sibling)
                ->withProperties([
                    'from' => $siblingFrom->value,
                    'to' => ApplicationStatus::Rejected->value,
                    'reason' => $reason,
                ])
                ->log('application.status_changed');

            $sibling->user->notify(
                new ApplicationStatusChanged($sibling, $siblingFrom, ApplicationStatus::Rejected),
            );
        }
    }
}
