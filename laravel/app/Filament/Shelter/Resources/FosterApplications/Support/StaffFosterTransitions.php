<?php

declare(strict_types=1);

namespace App\Filament\Shelter\Resources\FosterApplications\Support;

use App\Actions\Fostering\AdvanceFosterApplication;
use App\Enums\FosterApplicationStatus;

/**
 * Grafo delle transizioni offerte allo STAFF dell'ente nell'UI di gestione degli
 * affidi, guidate da {@see AdvanceFosterApplication}.
 *
 * Specchio di App\Filament\Shelter\Resources\AdoptionApplications\Support\StaffTransitions.
 * È un SOTTOINSIEME del grafo di dominio (App\States\Foster\FosterApplicationState):
 * copre SOLO la catena di revisione/decisione, coerentemente con
 * AdvanceFosterApplication che rifiuta Active/Completed/Withdrawn. In particolare
 * ESCLUDE di proposito:
 *   - Active: avvio dell'affido fisico → azione dedicata "Avvia affido"
 *     (StartFosterPlacement), visibile solo su Approved.
 *   - Completed: chiusura dell'affido fisico → azione dedicata "Concludi affido"
 *     (EndFosterPlacement), visibile solo su Active.
 *   - Withdrawn: azione dell'AFFIDATARIO (WithdrawFosterApplication).
 *
 * Serve solo a decidere QUALI opzioni mostrare; la verità del ciclo di vita resta
 * la macchina a stati + AdvanceFosterApplication, che ricontrolla ogni transizione.
 * Isolarlo qui evita di duplicare la mappatura nella tabella e la tiene testabile.
 *
 * Grafo staff:
 *   Submitted   → UnderReview
 *   UnderReview → Approved | Rejected | Waitlisted
 *   Waitlisted  → UnderReview
 *   (Approved → Active è "Avvia affido"; Active → Completed è "Concludi affido";
 *    Rejected/Completed/Withdrawn → nessuna uscita staff)
 */
final class StaffFosterTransitions
{
    /**
     * Transizioni staff-legali dallo stato indicato (via AdvanceFosterApplication).
     *
     * @return list<FosterApplicationStatus>
     */
    public static function forStatus(FosterApplicationStatus $from): array
    {
        return match ($from) {
            FosterApplicationStatus::Submitted => [
                FosterApplicationStatus::UnderReview,
            ],
            FosterApplicationStatus::UnderReview => [
                FosterApplicationStatus::Approved,
                FosterApplicationStatus::Rejected,
                FosterApplicationStatus::Waitlisted,
            ],
            FosterApplicationStatus::Waitlisted => [
                FosterApplicationStatus::UnderReview,
            ],
            // Approved → Active (Avvia affido) e Active → Completed (Concludi affido)
            // hanno azioni dedicate; Rejected/Completed/Withdrawn sono terminali per
            // lo staff.
            FosterApplicationStatus::Approved,
            FosterApplicationStatus::Active,
            FosterApplicationStatus::Completed,
            FosterApplicationStatus::Rejected,
            FosterApplicationStatus::Withdrawn => [],
        };
    }

    /**
     * Opzioni value=>label per un Select, ristrette alle transizioni legali.
     *
     * @return array<string, string>
     */
    public static function options(FosterApplicationStatus $from): array
    {
        $options = [];

        foreach (self::forStatus($from) as $status) {
            $options[$status->value] = $status->getLabel();
        }

        return $options;
    }
}
