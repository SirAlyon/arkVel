<?php

declare(strict_types=1);

namespace App\Support\Calendar;

use App\Support\Calendar\Providers\ExternalCalendarProvider;
use Illuminate\Support\Carbon;

/**
 * Value object che descrive UN appuntamento come evento di calendario già
 * minimizzato (GDPR by design). È il contratto UNICO di "cosa lascia ArkVel":
 * lo consumano sia l'export iCalendar ({@see AppointmentCalendarExport}) sia i
 * provider esterni ({@see ExternalCalendarProvider})
 * — così la politica su quali dati escono è definita una sola volta e non può
 * divergere fra ICS e sync esterno.
 *
 * Prodotto SEMPRE e SOLO da {@see AppointmentEventMapper::toEventData()}: quello è
 * il punto in cui un Appointment viene minimizzato. Questo VO è deliberatamente
 * "povero" (nessun dato personale del richiedente): ciò che non è qui, per
 * costruzione, non finisce in nessun calendario.
 *
 * @see AppointmentEventMapper per la logica di minimizzazione.
 */
final readonly class CalendarEventData
{
    /**
     * @param  string  $uid  UID stabile e deterministico ("appointment-{id}@{dominio}").
     * @param  string  $summary  Titolo minimizzato "{tipo}: {animale}".
     * @param  Carbon  $start  Inizio dell'evento (scheduled_at).
     * @param  Carbon  $end  Fine = inizio + durata di slot del dominio.
     * @param  string|null  $location  Nome ente + città, MAI la via.
     * @param  string|null  $description  Descrizione minimale (tipo + animale).
     * @param  string  $status  Stato NEUTRO rispetto al dominio: 'confirmed'|'tentative'|'cancelled'.
     * @param  int  $appointmentId  Id di origine (traccia la sorgente, non finisce nell'evento).
     * @param  int  $sequence  Valore monotòno per far accettare gli update ai client (updated_at ts).
     */
    public function __construct(
        public string $uid,
        public string $summary,
        public Carbon $start,
        public Carbon $end,
        public ?string $location,
        public ?string $description,
        public string $status,
        public int $appointmentId,
        public int $sequence = 0,
    ) {}
}
