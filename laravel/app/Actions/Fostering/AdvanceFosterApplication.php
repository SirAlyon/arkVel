<?php

declare(strict_types=1);

namespace App\Actions\Fostering;

use App\Enums\FosterApplicationStatus;
use App\Exceptions\InvalidFosterApplicationTransition;
use App\Models\FosterApplication;
use App\Models\User;
use App\Notifications\FosterApplicationStatusChanged;
use App\States\Foster\Approved;
use App\States\Foster\FosterApplicationState;
use App\States\Foster\Rejected;
use App\States\Foster\Submitted;
use App\States\Foster\UnderReview;
use App\States\Foster\Waitlisted;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\Exceptions\CouldNotPerformTransition;

/**
 * Transizione FORWARD guidata dallo STAFF dell'ente su una richiesta di affido.
 *
 * Copre SOLO la catena di REVISIONE/DECISIONE: UnderReview, Approved, Rejected,
 * Waitlisted. Tre esiti sono ESCLUSI di proposito da questa action:
 *  - Active: è l'AVVIO dell'affido fisico (creazione FosterPlacement),
 *    responsabilità di StartFosterPlacement; qui viene rifiutata.
 *  - Completed: è la CHIUSURA dell'affido fisico (rientro dell'animale),
 *    responsabilità di EndFosterPlacement; qui viene rifiutata.
 *  - Withdrawn: è un'azione dell'AFFIDATARIO (WithdrawFosterApplication), non
 *    dello staff; qui viene rifiutata.
 *
 * Fa da specchio a App\Actions\Applications\AdvanceApplication (che esclude in
 * modo analogo Completed/Withdrawn). Le guardie e la mappatura enum→stato vengono
 * valutate PRIMA di mutare o loggare: una transizione illegale solleva
 * InvalidFosterApplicationTransition senza lasciare stato/log parziali
 * (transitionTo è atomica). NESSUN accoppiamento con lo stato dell'animale qui:
 * l'animale entra/esce dall'affido solo con Start/EndFosterPlacement. Su ogni
 * transizione riuscita si logga (activity) e si notifica l'affidatario.
 */
final class AdvanceFosterApplication
{
    /**
     * @throws InvalidFosterApplicationTransition target non consentito dal grafo,
     *                                            oppure Active/Completed/Withdrawn
     *                                            (fuori dalla competenza di questa
     *                                            action)
     */
    public function handle(
        FosterApplication $application,
        FosterApplicationStatus $to,
        User $actor,
        ?string $reason = null,
    ): FosterApplication {
        $from = $application->statusEnum();

        // Mappatura enum→classe di stato. Active/Completed/Withdrawn NON sono
        // target validi per questa action (vedi docblock): li trattiamo come
        // transizioni di dominio non consentite, coerenti con la superficie
        // d'errore.
        $stateClass = $this->stateClassFor($from, $to);

        return DB::transaction(function () use ($application, $from, $to, $stateClass, $actor, $reason): FosterApplication {
            // La transizione spatie è atomica: se il grafo la vieta solleva
            // CouldNotPerformTransition e NON tocca lo stato. La convertiamo nel
            // wrapper di dominio PRIMA di qualunque log/notifica.
            try {
                $application->status->transitionTo($stateClass);
            } catch (CouldNotPerformTransition) {
                throw InvalidFosterApplicationTransition::between($from, $to);
            }

            // Sugli esiti decisionali fissiamo la data/motivazione della decisione.
            if ($this->isDecision($to)) {
                $application->decided_at = now();
                $application->decision_reason = $reason;
                $application->save();
            }

            activity()
                ->performedOn($application)
                ->causedBy($actor)
                ->withProperties([
                    'from' => $from->value,
                    'to' => $to->value,
                    'reason' => $reason,
                ])
                ->log('foster_application.status_changed');

            $application->user->notify(
                new FosterApplicationStatusChanged($application, $from, $to),
            );

            return $application;
        });
    }

    /**
     * Enum→classe di stato concreta. Active/Completed/Withdrawn sono fuori
     * competenza: li surfacciamo come transizione non consentita (senza mutare
     * nulla).
     *
     * @return class-string<FosterApplicationState>
     *
     * @throws InvalidFosterApplicationTransition
     */
    private function stateClassFor(FosterApplicationStatus $from, FosterApplicationStatus $to): string
    {
        return match ($to) {
            FosterApplicationStatus::Submitted => Submitted::class,
            FosterApplicationStatus::UnderReview => UnderReview::class,
            FosterApplicationStatus::Approved => Approved::class,
            FosterApplicationStatus::Rejected => Rejected::class,
            FosterApplicationStatus::Waitlisted => Waitlisted::class,
            // Active = avvio affido (StartFosterPlacement); Completed = chiusura
            // affido (EndFosterPlacement); Withdrawn = azione affidatario
            // (WithdrawFosterApplication). Non gestiti qui.
            FosterApplicationStatus::Active,
            FosterApplicationStatus::Completed,
            FosterApplicationStatus::Withdrawn => throw InvalidFosterApplicationTransition::between($from, $to),
        };
    }

    /**
     * Gli stati che rappresentano una decisione dell'ente: qui registriamo
     * decided_at/decision_reason.
     */
    private function isDecision(FosterApplicationStatus $to): bool
    {
        return match ($to) {
            FosterApplicationStatus::Approved,
            FosterApplicationStatus::Rejected,
            FosterApplicationStatus::Waitlisted => true,
            default => false,
        };
    }
}
