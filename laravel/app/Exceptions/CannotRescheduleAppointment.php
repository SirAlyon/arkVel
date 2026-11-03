<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Appointment;
use DomainException;

/**
 * Sollevata quando si tenta di spostare (reschedule) un appuntamento che non è più
 * riprogrammabile: solo gli appuntamenti ATTIVI (Richiesto/Confermato) possono essere
 * spostati. Gli stati terminali (Completed/Cancelled/NoShow) sono immutabili.
 *
 * È una guardia di dominio di RescheduleAppointment, valutata PRIMA di mutare: uno
 * spostamento illegale non deve mai andare a buon fine né lasciare stato/log parziali.
 * Porta con sé lo stato corrente per messaggi parlanti.
 */
final class CannotRescheduleAppointment extends DomainException
{
    public static function terminal(Appointment $appointment): self
    {
        return new self(
            __('appointments.rescheduled.terminal', [
                'status' => $appointment->status->getLabel(),
            ]),
        );
    }
}
