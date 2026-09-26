<?php

declare(strict_types=1);

namespace App\Actions\Applications;

use App\Actions\Animals\TransitionAnimalStatus;
use App\Enums\AnimalStatus;
use App\Enums\ApplicationStatus;
use App\Exceptions\AnimalAlreadyHasPendingAdoption;
use App\Exceptions\InvalidAnimalStatusTransition;
use App\Exceptions\InvalidApplicationTransition;
use App\Models\AdoptionApplication;
use App\Models\User;
use App\Notifications\ApplicationStatusChanged;
use App\States\Application\AdoptionPending;
use App\States\Application\ApplicationState;
use App\States\Application\Approved;
use App\States\Application\Completed;
use App\States\Application\Interview;
use App\States\Application\Rejected;
use App\States\Application\Submitted;
use App\States\Application\UnderReview;
use App\States\Application\Waitlisted;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\Exceptions\CouldNotPerformTransition;

/**
 * Transizione FORWARD guidata dallo STAFF dell'ente su una candidatura.
 *
 * Copre l'intera catena decisionale (UnderReview → Interview → Approved →
 * AdoptionPending) e gli esiti (Rejected, Waitlisted). Due esiti sono
 * ESCLUSI di proposito da questa action:
 *  - Completed: è FINALIZZAZIONE dell'adozione (creazione Adoption + PDF),
 *    responsabilità della slice 10; qui viene rifiutata.
 *  - Withdrawn: è un'azione dell'ADOTTANTE (WithdrawApplication), non dello
 *    staff; qui viene rifiutata.
 *
 * Le guardie e la mappatura enum→stato vengono valutate PRIMA di mutare o
 * loggare: una transizione illegale solleva InvalidApplicationTransition senza
 * lasciare stato/log parziali (transitionTo è atomica). Su ogni transizione
 * riuscita si logga (activity) e si notifica l'adottante.
 *
 * Accoppiamento con lo stato dell'ANIMALE (slice 10): quando la candidatura
 * entra in AdoptionPending, l'animale viene RISERVATO
 * (AnimalStatus::AdoptionPending) via TransitionAnimalStatus, DENTRO la stessa
 * transazione. Così la successiva finalizzazione (FinalizeAdoption) può
 * legalmente portare l'animale AdoptionPending → Adopted. Una riserva impossibile
 * (animale non in uno stato riservabile) fa rollback dell'intero avanzamento.
 */
final class AdvanceApplication
{
    public function __construct(
        private readonly TransitionAnimalStatus $transitionAnimalStatus,
    ) {}

    /**
     * @throws InvalidApplicationTransition target non consentito dal grafo,
     *                                      oppure Completed/Withdrawn (fuori
     *                                      dalla competenza di questa action)
     * @throws AnimalAlreadyHasPendingAdoption quando si porterebbe in
     *                                         AdoptionPending un animale che ha
     *                                         già un'adozione attiva/completata
     * @throws InvalidAnimalStatusTransition quando l'animale non è in uno stato
     *                                       riservabile (propagata, fa rollback)
     */
    public function handle(
        AdoptionApplication $application,
        ApplicationStatus $to,
        User $actor,
        ?string $reason = null,
    ): AdoptionApplication {
        $from = $application->statusEnum();

        // Mappatura enum→classe di stato. Completed e Withdrawn NON sono target
        // validi per questa action (vedi docblock): li trattiamo come transizioni
        // di dominio non consentite, coerenti con la superficie d'errore.
        $stateClass = $this->stateClassFor($from, $to);

        // Guardia "una sola adozione attiva per animale": prima di portare QUESTA
        // candidatura in AdoptionPending, verifica che nessun'ALTRA candidatura
        // dello stesso animale sia già in AdoptionPending o Completed.
        if ($to === ApplicationStatus::AdoptionPending) {
            $this->assertNoOtherPendingAdoption($application);
        }

        return DB::transaction(function () use ($application, $from, $to, $stateClass, $actor, $reason): AdoptionApplication {
            // La transizione spatie è atomica: se il grafo la vieta solleva
            // CouldNotPerformTransition e NON tocca lo stato. La convertiamo nel
            // wrapper di dominio PRIMA di qualunque log/notifica.
            try {
                $application->status->transitionTo($stateClass);
            } catch (CouldNotPerformTransition) {
                throw InvalidApplicationTransition::between($from, $to);
            }

            // Sugli esiti decisionali fissiamo la data/motivazione della decisione.
            if ($this->isDecision($to)) {
                $application->decided_at = now();
                $application->decision_reason = $reason;
                $application->save();
            }

            // Accoppiamento con l'animale: entrando in AdoptionPending la
            // candidatura RISERVA l'animale (→ AnimalStatus::AdoptionPending),
            // così FinalizeAdoption potrà poi fare AdoptionPending → Adopted.
            // Dentro la transazione: una riserva impossibile fa rollback
            // dell'intero avanzamento (InvalidAnimalStatusTransition propaga).
            if ($to === ApplicationStatus::AdoptionPending) {
                $this->transitionAnimalStatus->handle(
                    $application->animal,
                    AnimalStatus::AdoptionPending,
                );
            }

            activity()
                ->performedOn($application)
                ->causedBy($actor)
                ->withProperties([
                    'from' => $from->value,
                    'to' => $to->value,
                    'reason' => $reason,
                ])
                ->log('application.status_changed');

            $application->user->notify(
                new ApplicationStatusChanged($application, $from, $to),
            );

            return $application;
        });
    }

    /**
     * Enum→classe di stato concreta. Completed/Withdrawn sono fuori competenza:
     * li surfacciamo come transizione non consentita (senza mutare nulla).
     *
     * @return class-string<ApplicationState>
     *
     * @throws InvalidApplicationTransition
     */
    private function stateClassFor(ApplicationStatus $from, ApplicationStatus $to): string
    {
        return match ($to) {
            ApplicationStatus::Submitted => Submitted::class,
            ApplicationStatus::UnderReview => UnderReview::class,
            ApplicationStatus::Interview => Interview::class,
            ApplicationStatus::Approved => Approved::class,
            ApplicationStatus::AdoptionPending => AdoptionPending::class,
            ApplicationStatus::Rejected => Rejected::class,
            ApplicationStatus::Waitlisted => Waitlisted::class,
            // Completed = finalizzazione (slice 10); Withdrawn = azione adottante
            // (WithdrawApplication). Non gestiti qui.
            ApplicationStatus::Completed,
            ApplicationStatus::Withdrawn => throw InvalidApplicationTransition::between($from, $to),
        };
    }

    /**
     * Gli stati che rappresentano una decisione dell'ente: qui registriamo
     * decided_at/decision_reason.
     */
    private function isDecision(ApplicationStatus $to): bool
    {
        return match ($to) {
            ApplicationStatus::Approved,
            ApplicationStatus::Rejected,
            ApplicationStatus::Waitlisted => true,
            default => false,
        };
    }

    /**
     * @throws AnimalAlreadyHasPendingAdoption
     */
    private function assertNoOtherPendingAdoption(AdoptionApplication $application): void
    {
        /** @var AdoptionApplication|null $conflicting */
        $conflicting = AdoptionApplication::query()
            ->where('animal_id', $application->animal_id)
            ->whereKeyNot($application->getKey())
            ->whereState('status', [AdoptionPending::class, Completed::class])
            ->first();

        if ($conflicting !== null) {
            throw AnimalAlreadyHasPendingAdoption::for($application->animal, $conflicting->getKey());
        }
    }
}
