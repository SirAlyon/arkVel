<?php

declare(strict_types=1);

namespace App\Actions\Appointments;

use App\Enums\AppointmentStatus;
use App\Exceptions\InvalidAppointmentTransition;
use App\Models\Appointment;
use App\Models\User;
use App\Notifications\AppointmentNoShow;
use Illuminate\Support\Facades\DB;

/**
 * Registra l'ASSENZA del richiedente a un appuntamento confermato (azione dello
 * STAFF dell'ente).
 *
 * Esiste un'action dedicata perché lo stato NoShow del grafo
 * (App\Enums\AppointmentStatus) sarebbe altrimenti IRRAGGIUNGIBILE: il nucleo del
 * flusso è Request/Confirm/Cancel/Complete, ma l'enum prevede anche no_show come
 * esito terminale di un appuntamento confermato non onorato. Single
 * responsibility: unico punto in cui un appuntamento passa a NoShow. La guardia
 * interroga il grafo dell'enum (canTransitionTo) PRIMA di mutare o loggare: solo un
 * appuntamento Confermato può diventare NoShow, altrimenti
 * InvalidAppointmentTransition senza stato/log/notifica parziali. Su successo si
 * logga (activity) e si notifica il RICHIEDENTE.
 */
final class MarkAppointmentNoShow
{
    /**
     * @throws InvalidAppointmentTransition quando lo stato corrente non consente
     *                                      il passaggio a NoShow
     */
    public function handle(Appointment $appointment, User $actor): Appointment
    {
        $from = $appointment->status;

        if (! $from->canTransitionTo(AppointmentStatus::NoShow)) {
            throw InvalidAppointmentTransition::between($from, AppointmentStatus::NoShow);
        }

        return DB::transaction(function () use ($appointment, $from, $actor): Appointment {
            $appointment->status = AppointmentStatus::NoShow;
            $appointment->save();

            activity()
                ->performedOn($appointment)
                ->causedBy($actor)
                ->withProperties([
                    'from' => $from->value,
                    'to' => AppointmentStatus::NoShow->value,
                ])
                ->log('appointment.no_show');

            $appointment->user->notify(new AppointmentNoShow($appointment));

            return $appointment;
        });
    }
}
