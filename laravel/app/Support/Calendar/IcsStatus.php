<?php

declare(strict_types=1);

namespace App\Support\Calendar;

use App\Support\Calendar\Providers\CalDavProvider;

/**
 * Proiezione dello stato NEUTRO di dominio ({@see CalendarEventData::$status},
 * 'confirmed'|'tentative'|'cancelled') sui valori STATUS di iCalendar (RFC-5545
 * §3.8.1.11): CONFIRMED / TENTATIVE / CANCELLED.
 *
 * Estratta in un unico punto perché serve a PIÙ serializzatori ICS: l'export del feed
 * d'ente/single ({@see AppointmentCalendarExport}) e il push CalDAV
 * ({@see CalDavProvider}), che costruisce il VEVENT via
 * {@see IcsWriter}. Un solo posto → nessuna divergenza nella mappatura degli stati fra i
 * due percorsi.
 */
final class IcsStatus
{
    /**
     * Proietta lo stato neutro sui valori STATUS di iCalendar. Default difensivo
     * CONFIRMED per qualsiasi valore non riconosciuto.
     */
    public static function fromNeutral(string $neutralStatus): string
    {
        return match ($neutralStatus) {
            'tentative' => 'TENTATIVE',
            'cancelled' => 'CANCELLED',
            default => 'CONFIRMED',
        };
    }
}
