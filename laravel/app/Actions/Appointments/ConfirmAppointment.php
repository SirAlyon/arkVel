<?php

declare(strict_types=1);

namespace App\Actions\Appointments;

use App\Enums\AppointmentStatus;
use App\Exceptions\InvalidAppointmentTransition;
use App\Models\Appointment;
use App\Models\User;
use App\Notifications\AppointmentConfirmed;
use Illuminate\Support\Facades\DB;

/**
 * Conferma un appuntamento richiesto (azione dello STAFF dell'ente).
 *
 * Single responsibility: unico punto in cui un appuntamento passa a Confirmed. La
 * guardia interroga il grafo dell'enum (canTransitionTo) PRIMA di mutare o
 * loggare: una transizione illegale solleva InvalidAppointmentTransition senza
 * lasciare stato/log/notifica parziali. Su successo si logga (activity) e si
 * notifica il RICHIEDENTE. Fa da specchio ad AdvanceFosterApplication (guardia +
 * txn + log + notifica).
 */
final class ConfirmAppointment
{
    /**
     * @throws InvalidAppointmentTransition quando lo stato corrente non consente
     *                                      il passaggio a Confirmed
     */
    public function handle(Appointment $appointment, User $actor): Appointment
    {
        $from = $appointment->status;

        if (! $from->canTransitionTo(AppointmentStatus::Confirmed)) {
            throw InvalidAppointmentTransition::between($from, AppointmentStatus::Confirmed);
        }

        return DB::transaction(function () use ($appointment, $from, $actor): Appointment {
            $appointment->status = AppointmentStatus::Confirmed;
            $appointment->save();

            activity()
                ->performedOn($appointment)
                ->causedBy($actor)
                ->withProperties([
                    'from' => $from->value,
                    'to' => AppointmentStatus::Confirmed->value,
                ])
                ->log('appointment.confirmed');

            $appointment->user->notify(new AppointmentConfirmed($appointment));

            return $appointment;
        });
    }
}
