<?php

declare(strict_types=1);

namespace App\Support\Appointments;

use App\Exceptions\AppointmentSlotUnavailable;
use App\Models\Animal;
use App\Models\Appointment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Guardia di dominio, stateless, che verifica la disponibilità di uno slot: nessun
 * appuntamento ATTIVO (Richiesto/Confermato) dello stesso ANIMALE né dello stesso
 * RICHIEDENTE può occupare una finestra sovrapposta.
 *
 * Fonte UNICA della logica di overlap, condivisa da RequestAppointment (creazione) e
 * da RescheduleAppointment (spostamento): la matematica della finestra e le entità
 * controllate vivono qui una sola volta (DRY), non duplicate nelle Action.
 *
 * La finestra occupata da un appuntamento è [scheduled_at, scheduled_at + SLOT).
 * Due finestre di pari durata (SLOT) si sovrappongono quando lo start esistente cade
 * STRETTAMENTE dentro (at - SLOT, at + SLOT). Gli stati terminali
 * (Completed/Cancelled/NoShow) liberano lo slot (scope `active`) e non bloccano.
 *
 * Riscrivendo un appuntamento esistente (reschedule) esso NON deve confliggere con se
 * stesso: {@see ensureAvailable()} accetta un $exceptAppointmentId da escludere dalla
 * ricerca di overlap. In creazione l'id è null (nessuna riga da escludere).
 *
 * NOTA sulla concorrenza: la serializzazione (lockForUpdate su animale + richiedente)
 * resta responsabilità dell'Action chiamante, dentro la transazione, PRIMA di
 * invocare questa guardia — così la finestra TOCTOU è chiusa nel punto in cui si
 * scrive.
 */
final class AppointmentOverlapGuard
{
    /**
     * Solleva se lo slot $at non è disponibile per l'animale o per il richiedente,
     * escludendo dall'analisi l'appuntamento $exceptAppointmentId (se fornito).
     *
     * @throws AppointmentSlotUnavailable quando lo slot si sovrappone a un altro
     *                                    appuntamento attivo (animale o richiedente)
     */
    public function ensureAvailable(
        Animal $animal,
        User $requester,
        CarbonInterface $at,
        ?int $exceptAppointmentId = null,
    ): void {
        if ($this->hasOverlap($at, 'animal_id', $animal->getKey(), $exceptAppointmentId)) {
            throw AppointmentSlotUnavailable::forAnimal($animal, $at);
        }

        if ($this->hasOverlap($at, 'user_id', $requester->getKey(), $exceptAppointmentId)) {
            throw AppointmentSlotUnavailable::forUser($requester, $at);
        }
    }

    /**
     * Esiste un appuntamento ATTIVO per l'entità indicata (diverso da
     * $exceptAppointmentId) la cui finestra si sovrappone allo slot richiesto? La
     * finestra ha durata Appointment::SLOT_MINUTES a partire da scheduled_at; due
     * finestre di pari durata si sovrappongono se lo start esistente cade STRETTAMENTE
     * dentro (at - SLOT, at + SLOT).
     *
     * @param  'animal_id'|'user_id'  $column
     */
    private function hasOverlap(
        CarbonInterface $at,
        string $column,
        int $id,
        ?int $exceptAppointmentId,
    ): bool {
        $windowStart = $at->copy()->subMinutes(Appointment::SLOT_MINUTES);
        $windowEnd = $at->copy()->addMinutes(Appointment::SLOT_MINUTES);

        return Appointment::query()
            ->active()
            ->where($column, $id)
            ->when(
                $exceptAppointmentId !== null,
                fn (Builder $query): Builder => $query->whereKeyNot($exceptAppointmentId),
            )
            ->where(function (Builder $query) use ($windowStart, $windowEnd): void {
                $query
                    ->where('scheduled_at', '>', $windowStart)
                    ->where('scheduled_at', '<', $windowEnd);
            })
            ->exists();
    }
}
