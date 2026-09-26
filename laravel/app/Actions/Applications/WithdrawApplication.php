<?php

declare(strict_types=1);

namespace App\Actions\Applications;

use App\Enums\ApplicationStatus;
use App\Exceptions\CannotWithdrawApplication;
use App\Exceptions\InvalidApplicationTransition;
use App\Models\AdoptionApplication;
use App\Models\User;
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
 */
final class WithdrawApplication
{
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
}
