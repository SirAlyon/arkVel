<?php

declare(strict_types=1);

namespace App\Actions\Appointments;

use App\Enums\AppointmentStatus;
use App\Exceptions\InvalidAppointmentTransition;
use App\Models\Appointment;
use App\Models\User;
use App\Notifications\AppointmentCancelled;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Annulla un appuntamento. Può essere richiesto da ENTRAMBE le parti: il
 * richiedente proprietario oppure lo staff dell'ente (l'autorizzazione è nella
 * AppointmentPolicy::cancel).
 *
 * Single responsibility: unico punto in cui un appuntamento passa a Cancelled. La
 * guardia interroga il grafo dell'enum (canTransitionTo) PRIMA di mutare o
 * loggare: una transizione illegale (stato terminale) solleva
 * InvalidAppointmentTransition senza lasciare stato/log/notifica parziali.
 *
 * Notifica alla CONTROPARTE: se l'attore è il richiedente → notifica ai membri
 * verificati dell'ente; se l'attore è lo staff → notifica al richiedente. Così chi
 * annulla non riceve la propria notifica. Accodata after-commit.
 */
final class CancelAppointment
{
    /**
     * @throws InvalidAppointmentTransition quando lo stato corrente non consente
     *                                      il passaggio a Cancelled
     */
    public function handle(Appointment $appointment, User $actor, ?string $reason = null): Appointment
    {
        $from = $appointment->status;

        if (! $from->canTransitionTo(AppointmentStatus::Cancelled)) {
            throw InvalidAppointmentTransition::between($from, AppointmentStatus::Cancelled);
        }

        return DB::transaction(function () use ($appointment, $from, $actor, $reason): Appointment {
            $appointment->status = AppointmentStatus::Cancelled;
            $appointment->save();

            activity()
                ->performedOn($appointment)
                ->causedBy($actor)
                ->withProperties([
                    'from' => $from->value,
                    'to' => AppointmentStatus::Cancelled->value,
                    'reason' => $reason,
                ])
                ->log('appointment.cancelled');

            // Notifica alla controparte. Recipient vuoto → Notification::send è un
            // no-op sicuro.
            Notification::send(
                $this->counterparty($appointment, $actor),
                new AppointmentCancelled($appointment, $reason),
            );

            return $appointment;
        });
    }

    /**
     * Destinatari della notifica di annullamento: la parte OPPOSTA a chi annulla.
     * Se annulla il richiedente proprietario → i membri verificati dell'ente. Se
     * annulla chiunque altro (staff) → il richiedente proprietario.
     *
     * @return Collection<int, User>
     */
    private function counterparty(Appointment $appointment, User $actor): Collection
    {
        if ($actor->is($appointment->user)) {
            return $appointment->organization->verifiedStaff()->get();
        }

        return collect([$appointment->user]);
    }
}
