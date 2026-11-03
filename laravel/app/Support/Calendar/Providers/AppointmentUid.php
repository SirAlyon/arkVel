<?php

declare(strict_types=1);

namespace App\Support\Calendar\Providers;

use App\Support\Calendar\AppointmentEventMapper;

/**
 * Helper condiviso per l'UID stabile degli eventi ArkVel (`appointment-{id}@{domain}`).
 *
 * L'UID è generato in un unico punto ({@see AppointmentEventMapper})
 * e viaggia su Google (iCalUID) e CalDAV (UID del VEVENT / basename dell'href). In fase di
 * pull (Cal-7) va invertito per ritrovare l'appuntamento ArkVel quando l'evento remoto non
 * porta l'id in altra forma. Isolato qui (DRY) così la convenzione ha una sola definizione.
 */
final class AppointmentUid
{
    /**
     * Estrae l'id dell'appuntamento ArkVel dall'UID `appointment-{id}@…`. Null se l'UID
     * non segue la convenzione (evento creato da terzi direttamente nel calendario).
     */
    public static function toAppointmentId(?string $uid): ?int
    {
        if ($uid === null || $uid === '') {
            return null;
        }

        return preg_match('/^appointment-(\d+)@/', $uid, $matches) === 1
            ? (int) $matches[1]
            : null;
    }
}
