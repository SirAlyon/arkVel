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
use App\States\Application\AdoptionPending;
use App\States\Application\Completed;
use App\States\Application\Withdrawn;
use Illuminate\Support\Facades\DB;
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
 * La notifica all'ente ("candidatura ritirata") arriva nella slice 11: qui si
 * logga soltanto.
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

            return $application;
        });
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
