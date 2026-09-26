<?php

declare(strict_types=1);

namespace App\Actions\Applications;

use App\Actions\Animals\TransitionAnimalStatus;
use App\Enums\AnimalStatus;
use App\Enums\ApplicationStatus;
use App\Exceptions\CannotWithdrawApplication;
use App\Exceptions\InvalidApplicationTransition;
use App\Models\AdoptionApplication;
use App\Models\User;
use App\Notifications\ApplicationWithdrawn;
use App\States\Application\AdoptionPending;
use App\States\Application\Completed;
use App\States\Application\Withdrawn;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Spatie\ModelStates\Exceptions\CouldNotPerformTransition;

/**
 * Ritiro di una candidatura da parte dell'ADOTTANTE proprietario.
 *
 * Single responsibility: unico punto in cui una candidatura passa a Withdrawn.
 * Guardia di ownership esplicita (solo il proprietario ritira) valutata prima
 * di mutare; il ritiro da uno stato terminale (Completed/Rejected/Withdrawn)
 * fallisce perché il grafo non lo consente e viene surfacciato come
 * InvalidApplicationTransition, senza lasciare stato/log parziali.
 *
 * Notifica (slice 11): dopo il ritiro (e l'eventuale rilascio dell'animale), lo
 * STAFF dell'ente riceve {@see ApplicationWithdrawn} (mail + database). I
 * destinatari sono l'operatore in carico (assignedTo) se assegnato, altrimenti i
 * membri verificati dell'ente ({@see Organization::verifiedStaff()}). La notifica
 * è accodata after-commit: l'ordinamento rispetto alla transazione è garantito
 * dalla notifica stessa ($afterCommit), l'Action resta lineare.
 *
 * Accoppiamento con lo stato dell'ANIMALE (slice 10): se la candidatura ritirata
 * aveva RISERVATO l'animale (animale in AnimalStatus::AdoptionPending e nessun'
 * altra candidatura/adozione lo trattiene), il ritiro lo RILASCIA
 * (AdoptionPending → Available) via TransitionAnimalStatus, dentro la stessa
 * transazione. Un animale già Adopted non viene mai toccato (transizione
 * terminale): la guardia dell'enum lo garantisce e qui ci fermiamo prima.
 */
final class WithdrawApplication
{
    public function __construct(
        private readonly TransitionAnimalStatus $transitionAnimalStatus,
    ) {}

    /**
     * @throws CannotWithdrawApplication quando l'attore non è il proprietario
     * @throws InvalidApplicationTransition quando lo stato corrente è terminale
     */
    public function handle(
        AdoptionApplication $application,
        User $adopter,
        ?string $reason = null,
    ): AdoptionApplication {
        if (! $adopter->is($application->user)) {
            throw CannotWithdrawApplication::notOwner($application, $adopter);
        }

        $from = $application->statusEnum();

        return DB::transaction(function () use ($application, $from, $adopter, $reason): AdoptionApplication {
            try {
                $application->status->transitionTo(Withdrawn::class);
            } catch (CouldNotPerformTransition) {
                throw InvalidApplicationTransition::between($from, ApplicationStatus::Withdrawn);
            }

            $application->decided_at = now();
            $application->decision_reason = $reason;
            $application->save();

            // Rilascio dell'animale: se QUESTA candidatura teneva riservato
            // l'animale, tornarlo disponibile. Dentro la transazione: il rilascio
            // e il ritiro sono un unico atto atomico.
            $this->releaseReservedAnimal($application);

            activity()
                ->performedOn($application)
                ->causedBy($adopter)
                ->withProperties([
                    'from' => $from->value,
                    'reason' => $reason,
                ])
                ->log('application.withdrawn');

            // Notifica allo staff dell'ente. Accodata after-commit: parte solo dopo
            // il commit di questa transazione (nessun invio mail sincrono qui).
            // Recipient vuoto → Notification::send è un no-op sicuro.
            Notification::send(
                $this->recipients($application),
                new ApplicationWithdrawn($application),
            );

            return $application;
        });
    }

    /**
     * Destinatari lato ente della notifica di ritiro: l'operatore in carico
     * (assignedTo) se assegnato, altrimenti i membri VERIFICATI dell'ente
     * (owner + staff). Restituita come Collection di User, coerente col contratto
     * di Notification::send.
     *
     * @return Collection<int, User>
     */
    private function recipients(AdoptionApplication $application): Collection
    {
        if ($application->assignedTo !== null) {
            return collect([$application->assignedTo]);
        }

        return $application->organization->verifiedStaff()->get();
    }

    /**
     * Rilascia l'animale (AdoptionPending → Available) SOLO se la candidatura
     * ritirata era quella che lo teneva riservato: l'animale è in AdoptionPending
     * e nessun'ALTRA candidatura è ancora in AdoptionPending e nessuna adozione lo
     * trattiene. Un animale Adopted (adozione già finalizzata) non viene toccato.
     */
    private function releaseReservedAnimal(AdoptionApplication $application): void
    {
        $animal = $application->animal;

        if ($animal->status !== AnimalStatus::AdoptionPending) {
            return;
        }

        // Un'altra candidatura ancora in AdoptionPending mantiene la riserva.
        $otherPending = AdoptionApplication::query()
            ->where('animal_id', $animal->getKey())
            ->whereKeyNot($application->getKey())
            ->whereState('status', AdoptionPending::class)
            ->exists();

        // Un'adozione finalizzata da un'altra candidatura trattiene l'animale.
        $completed = AdoptionApplication::query()
            ->where('animal_id', $animal->getKey())
            ->whereKeyNot($application->getKey())
            ->whereState('status', Completed::class)
            ->exists();

        if ($otherPending || $completed) {
            return;
        }

        $this->transitionAnimalStatus->handle($animal, AnimalStatus::Available);
    }
}
