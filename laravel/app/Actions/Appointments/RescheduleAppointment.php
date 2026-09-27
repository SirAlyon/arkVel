<?php

declare(strict_types=1);

namespace App\Actions\Appointments;

use App\Enums\AppointmentStatus;
use App\Exceptions\AppointmentSlotUnavailable;
use App\Exceptions\CannotRescheduleAppointment;
use App\Exceptions\InvalidAppointmentSlot;
use App\Models\Animal;
use App\Models\Appointment;
use App\Models\User;
use App\Notifications\AppointmentRescheduled;
use App\Support\Appointments\AppointmentOverlapGuard;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Sposta un appuntamento ATTIVO a un nuovo orario (azione dello STAFF dell'ente, es.
 * drag-drop nel calendario). Single responsibility: unico punto in cui cambia lo
 * scheduled_at di un appuntamento esistente.
 *
 * Le guardie di dominio sono valutate PRIMA di scrivere, così un rifiuto non lascia
 * stato/log/notifica parziali:
 *  1. l'appuntamento dev'essere RIPROGRAMMABILE, cioè attivo — Richiesto o Confermato
 *     (altrimenti CannotRescheduleAppointment::terminal); gli stati terminali sono
 *     immutabili;
 *  2. il nuovo slot dev'essere STRETTAMENTE nel futuro (InvalidAppointmentSlot);
 *  3. il nuovo slot non deve sovrapporsi ad altri appuntamenti attivi
 *     (AppointmentSlotUnavailable), ESCLUDENDO l'appuntamento stesso — non deve
 *     confliggere con la propria vecchia finestra.
 *
 * La guardia di overlap è condivisa con RequestAppointment via
 * {@see AppointmentOverlapGuard} (DRY). La serializzazione (lockForUpdate su animale +
 * richiedente) avviene dentro la transazione PRIMA della guardia, così due spostamenti
 * concorrenti sullo stesso slot non superano entrambi il controllo (TOCTOU chiuso).
 *
 * Al successo: reset di reminder_sent_at a null (il promemoria dell'imminenza deve
 * ripartire per il nuovo orario), log activity (from/to ISO + attore) e notifica al
 * RICHIEDENTE con {@see AppointmentRescheduled}. Fa da specchio a ConfirmAppointment
 * (guardia + txn + log + notifica).
 */
final class RescheduleAppointment
{
    public function __construct(
        private readonly AppointmentOverlapGuard $overlapGuard,
    ) {}

    /**
     * @throws CannotRescheduleAppointment quando l'appuntamento non è più attivo
     * @throws InvalidAppointmentSlot quando il nuovo slot non è nel futuro
     * @throws AppointmentSlotUnavailable quando il nuovo slot si sovrappone a un altro
     *                                    appuntamento attivo (animale o richiedente)
     */
    public function handle(Appointment $appointment, User $actor, CarbonInterface $newScheduledAt): Appointment
    {
        // Guardia di stato: solo gli appuntamenti attivi si spostano. I terminali
        // (Completed/Cancelled/NoShow) sono immutabili.
        if (! $this->isReschedulable($appointment->status)) {
            throw CannotRescheduleAppointment::terminal($appointment);
        }

        // Guardia temporale: nessuna mutazione se il nuovo slot non è strettamente futuro.
        if ($newScheduledAt->lessThanOrEqualTo(now())) {
            throw InvalidAppointmentSlot::inThePast($newScheduledAt);
        }

        $from = $appointment->scheduled_at;
        // Il cast Eloquent di scheduled_at è Illuminate\Support\Carbon: normalizziamo
        // l'input (CarbonInterface) al tipo concreto prima dell'assegnazione.
        $target = Carbon::instance($newScheduledAt);

        return DB::transaction(function () use ($appointment, $actor, $target, $from): Appointment {
            // Serializziamo sull'animale E sul richiedente PRIMA della guardia di
            // overlap, così due spostamenti simultanei sullo stesso slot non superano
            // entrambi il controllo (stesso pattern di RequestAppointment).
            Animal::query()->whereKey($appointment->animal_id)->lockForUpdate()->first();
            User::query()->whereKey($appointment->user_id)->lockForUpdate()->first();

            // Overlap ESCLUDENDO l'appuntamento stesso: non deve confliggere con la
            // propria vecchia finestra.
            $this->overlapGuard->ensureAvailable(
                $appointment->animal,
                $appointment->user,
                $target,
                exceptAppointmentId: $appointment->getKey(),
            );

            $appointment->scheduled_at = $target;
            // Il promemoria dell'imminenza deve ripartire per il nuovo orario.
            $appointment->reminder_sent_at = null;
            $appointment->save();

            activity()
                ->performedOn($appointment)
                ->causedBy($actor)
                ->withProperties([
                    'from' => $from->toIso8601String(),
                    'to' => $appointment->scheduled_at->toIso8601String(),
                ])
                ->log('appointment.rescheduled');

            $appointment->user->notify(new AppointmentRescheduled($appointment, $from));

            return $appointment;
        });
    }

    /**
     * Un appuntamento è riprogrammabile finché è attivo (Richiesto o Confermato).
     */
    private function isReschedulable(AppointmentStatus $status): bool
    {
        return in_array($status, [AppointmentStatus::Requested, AppointmentStatus::Confirmed], true);
    }
}
