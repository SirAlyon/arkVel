<?php

declare(strict_types=1);

namespace App\Actions\Appointments;

use App\Enums\AppointmentStatus;
use App\Exceptions\InvalidAppointmentTransition;
use App\Models\Appointment;
use App\Models\User;
use App\Notifications\AppointmentCompleted;
use Illuminate\Support\Facades\DB;

/**
 * Segna un appuntamento come completato (azione dello STAFF dell'ente, dopo che
 * l'incontro è avvenuto).
 *
 * Single responsibility: unico punto in cui un appuntamento passa a Completed. La
 * guardia interroga il grafo dell'enum (canTransitionTo) PRIMA di mutare o
 * loggare: solo un appuntamento Confermato può essere completato, altrimenti
 * InvalidAppointmentTransition senza stato/log/notifica parziali. Su successo si
 * logga (activity) e si notifica il RICHIEDENTE.
 */
final class CompleteAppointment
{
    /**
     * @throws InvalidAppointmentTransition quando lo stato corrente non consente
     *                                      il passaggio a Completed
     */
    public function handle(Appointment $appointment, User $actor): Appointment
    {
        $from = $appointment->status;

        if (! $from->canTransitionTo(AppointmentStatus::Completed)) {
            throw InvalidAppointmentTransition::between($from, AppointmentStatus::Completed);
        }

        return DB::transaction(function () use ($appointment, $from, $actor): Appointment {
            $appointment->status = AppointmentStatus::Completed;
            $appointment->save();

            activity()
                ->performedOn($appointment)
                ->causedBy($actor)
                ->withProperties([
                    'from' => $from->value,
                    'to' => AppointmentStatus::Completed->value,
                ])
                ->log('appointment.completed');

            $appointment->user->notify(new AppointmentCompleted($appointment));

            return $appointment;
        });
    }
}
