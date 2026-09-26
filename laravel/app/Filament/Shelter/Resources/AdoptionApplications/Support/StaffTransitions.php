<?php

declare(strict_types=1);

namespace App\Filament\Shelter\Resources\AdoptionApplications\Support;

use App\Enums\ApplicationStatus;

/**
 * Grafo delle transizioni offerte allo STAFF dell'ente nell'UI di gestione.
 *
 * E' un SOTTOINSIEME del grafo di dominio (App\States\Application\ApplicationState):
 * esclude di proposito Completed (finalizzazione dell'adozione, slice 10) e
 * Withdrawn (azione dell'ADOTTANTE), coerentemente con AdvanceApplication che
 * rifiuta questi due target. Serve solo a decidere QUALI opzioni mostrare; la
 * verita del ciclo di vita resta la macchina a stati + AdvanceApplication, che
 * ricontrolla ogni transizione. Isolarlo qui evita di duplicare la mappatura
 * nella tabella e la tiene testabile.
 *
 * Grafo staff:
 *   Submitted        → UnderReview
 *   UnderReview      → Interview | Rejected | Waitlisted
 *   Interview        → Approved | Rejected
 *   Approved         → AdoptionPending
 *   Waitlisted       → UnderReview
 *   (AdoptionPending, Completed, Rejected, Withdrawn → nessuna uscita staff)
 */
final class StaffTransitions
{
    /**
     * Transizioni staff-legali dallo stato indicato.
     *
     * @return list<ApplicationStatus>
     */
    public static function forStatus(ApplicationStatus $from): array
    {
        return match ($from) {
            ApplicationStatus::Submitted => [
                ApplicationStatus::UnderReview,
            ],
            ApplicationStatus::UnderReview => [
                ApplicationStatus::Interview,
                ApplicationStatus::Rejected,
                ApplicationStatus::Waitlisted,
            ],
            ApplicationStatus::Interview => [
                ApplicationStatus::Approved,
                ApplicationStatus::Rejected,
            ],
            ApplicationStatus::Approved => [
                ApplicationStatus::AdoptionPending,
            ],
            ApplicationStatus::Waitlisted => [
                ApplicationStatus::UnderReview,
            ],
            // Nessuna transizione staff: AdoptionPending → Completed e' la
            // finalizzazione (slice 10); Completed/Rejected/Withdrawn sono
            // terminali per lo staff.
            ApplicationStatus::AdoptionPending,
            ApplicationStatus::Completed,
            ApplicationStatus::Rejected,
            ApplicationStatus::Withdrawn => [],
        };
    }

    /**
     * Opzioni value=>label per un Select, ristrette alle transizioni legali.
     *
     * @return array<string, string>
     */
    public static function options(ApplicationStatus $from): array
    {
        $options = [];

        foreach (self::forStatus($from) as $status) {
            $options[$status->value] = $status->getLabel();
        }

        return $options;
    }
}
