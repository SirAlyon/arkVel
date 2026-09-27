<?php

declare(strict_types=1);

namespace App\Actions\Appointments;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Exceptions\AppointmentSlotUnavailable;
use App\Exceptions\InvalidAppointmentSlot;
use App\Models\Animal;
use App\Models\Appointment;
use App\Models\User;
use App\Notifications\NewAppointmentRequested;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Richiede un appuntamento (visita/colloquio/controllo domiciliare) fra un
 * richiedente e un ente su un animale.
 *
 * Single responsibility: è l'unico punto in cui un appuntamento nasce. Le guardie
 * di dominio sono valutate PRIMA di scrivere, così un rifiuto non lascia righe
 * orfane:
 *  1. lo slot deve essere STRETTAMENTE nel futuro (InvalidAppointmentSlot);
 *  2. lo slot non deve sovrapporsi ad altri appuntamenti ATTIVI
 *     (Richiesto/Confermato) né dello stesso ANIMALE
 *     (AppointmentSlotUnavailable::forAnimal) né dello stesso RICHIEDENTE
 *     (AppointmentSlotUnavailable::forUser).
 *
 * La finestra occupata da un appuntamento è [scheduled_at, scheduled_at + SLOT).
 * Due finestre si sovrappongono quando newStart < existingEnd && existingStart <
 * newEnd; con durata fissa (SLOT) ciò equivale a: existingStart è STRETTAMENTE
 * dentro (newStart - SLOT, newStart + SLOT). Gli stati terminali
 * (Completed/Cancelled/NoShow) liberano lo slot (scope `active`) e non bloccano.
 *
 * L'organization_id è derivato dall'animale (coerenza con animal.organization_id).
 * Dopo la creazione si logga (activity) e si notificano i membri VERIFICATI
 * dell'ente ({@see Organization::verifiedStaff()}) con {@see NewAppointmentRequested}
 * (mail + database), accodata after-commit.
 */
final class RequestAppointment
{
    /**
     * @throws InvalidAppointmentSlot quando lo slot non è nel futuro
     * @throws AppointmentSlotUnavailable quando lo slot si sovrappone a un altro
     *                                    appuntamento attivo (animale o richiedente)
     */
    public function handle(
        User $requester,
        Animal $animal,
        AppointmentType $type,
        CarbonInterface $scheduledAt,
        ?string $notes = null,
    ): Appointment {
        // Guardia temporale: nessuna riga se lo slot non è strettamente futuro.
        if ($scheduledAt->lessThanOrEqualTo(now())) {
            throw InvalidAppointmentSlot::inThePast($scheduledAt);
        }

        return DB::transaction(function () use ($requester, $animal, $type, $scheduledAt, $notes): Appointment {
            // Concorrenza: serializziamo sull'animale E sul richiedente (lockForUpdate)
            // PRIMA di leggere gli appuntamenti concorrenti, così due richieste
            // simultanee sullo stesso slot non superano entrambe la guardia di overlap
            // (TOCTOU chiuso). Stesso pattern di StartFosterPlacement/AdvanceApplication.
            // Postgres onora il lock; SQLite lo ignora senza danni (test seriali).
            Animal::query()->whereKey($animal->getKey())->lockForUpdate()->first();
            User::query()->whereKey($requester->getKey())->lockForUpdate()->first();

            // Guardia di overlap PER ANIMALE: nessun altro appuntamento attivo
            // dell'animale può occupare una finestra sovrapposta.
            if ($this->hasOverlap($scheduledAt, 'animal_id', $animal->getKey())) {
                throw AppointmentSlotUnavailable::forAnimal($animal, $scheduledAt);
            }

            // Guardia di overlap PER RICHIEDENTE: nessun altro appuntamento attivo
            // del richiedente può occupare una finestra sovrapposta (anche su
            // animali diversi: la persona non può essere in due posti insieme).
            if ($this->hasOverlap($scheduledAt, 'user_id', $requester->getKey())) {
                throw AppointmentSlotUnavailable::forUser($requester, $scheduledAt);
            }

            $appointment = Appointment::query()->create([
                'animal_id' => $animal->getKey(),
                'user_id' => $requester->getKey(),
                'organization_id' => $animal->organization_id,
                'scheduled_at' => $scheduledAt,
                'type' => $type,
                'status' => AppointmentStatus::Requested,
                'notes' => $notes,
            ]);

            activity()
                ->performedOn($appointment)
                ->causedBy($requester)
                ->withProperties([
                    'type' => $type->value,
                    'scheduled_at' => $appointment->scheduled_at->toIso8601String(),
                ])
                ->log('appointment.requested');

            // Notifica ai membri verificati dell'ente (owner + staff). Accodata
            // after-commit: parte solo dopo il commit di questa transazione.
            // Recipient vuoto → Notification::send è un no-op sicuro.
            Notification::send(
                $animal->organization->verifiedStaff()->get(),
                new NewAppointmentRequested($appointment),
            );

            return $appointment;
        });
    }

    /**
     * Esiste un appuntamento ATTIVO per l'entità indicata la cui finestra si
     * sovrappone allo slot richiesto? La finestra ha durata Appointment::SLOT_MINUTES
     * a partire da scheduled_at; due finestre di pari durata si sovrappongono se lo
     * start esistente cade STRETTAMENTE dentro (newStart - SLOT, newStart + SLOT).
     *
     * @param  'animal_id'|'user_id'  $column
     */
    private function hasOverlap(CarbonInterface $scheduledAt, string $column, int $id): bool
    {
        $windowStart = $scheduledAt->copy()->subMinutes(Appointment::SLOT_MINUTES);
        $windowEnd = $scheduledAt->copy()->addMinutes(Appointment::SLOT_MINUTES);

        return Appointment::query()
            ->active()
            ->where($column, $id)
            ->where(function (Builder $query) use ($windowStart, $windowEnd): void {
                $query
                    ->where('scheduled_at', '>', $windowStart)
                    ->where('scheduled_at', '<', $windowEnd);
            })
            ->exists();
    }
}
