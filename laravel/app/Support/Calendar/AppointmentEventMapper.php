<?php

declare(strict_types=1);

namespace App\Support\Calendar;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Support\Calendar\Providers\ExternalCalendarProvider;

/**
 * Punto UNICO in cui un {@see Appointment} diventa un {@see CalendarEventData}
 * minimizzato (GDPR by design). Contiene la politica su QUALI dati lasciano ArkVel:
 * qualunque canale in uscita (feed/singolo .ics via {@see AppointmentCalendarExport}
 * e i provider esterni Google/CalDAV via
 * {@see ExternalCalendarProvider}) consuma il DTO
 * prodotto qui, così la minimizzazione è definita una volta sola e non può divergere.
 *
 * COSA FINISCE nell'evento (e cosa NO):
 *  - SUMMARY = "{tipo}: {animale}" (localizzato) — es. "Visita: Rex".
 *  - LOCATION = nome ente + città (MAI la via/indirizzo civico).
 *  - DESCRIPTION = tipo + animale, minimale.
 *  - UID = "appointment-{id}@{dominio app}" STABILE e deterministico: i client
 *    deduplicano/aggiornano invece di duplicare a ogni fetch del feed.
 *  - STATUS = stato NEUTRO ('confirmed'|'tentative'|'cancelled') derivato da
 *    AppointmentStatus; ogni canale lo rimappa nel proprio dialetto
 *    (ICS: CONFIRMED/TENTATIVE/CANCELLED — Google/CalDAV: confirmed/tentative/cancelled).
 *  - DTSTART = scheduled_at; DTEND = +SLOT_MINUTES (durata di dominio).
 *
 * MAI presenti: email, telefono, cognome del richiedente; note interne; indirizzo
 * civico dell'ente. Il richiedente non è nominato affatto.
 *
 * Servizio puro (nessuno stato, nessuna dipendenza I/O): riceve un model già
 * caricato e restituisce un VO. La risoluzione tenant-scoped e l'autorizzazione
 * vivono altrove (controller/pagine/job), non qui.
 */
final class AppointmentEventMapper
{
    /**
     * Mappa un appuntamento nel VO di evento minimizzato. Punto UNICO di verità del
     * mapping: qualunque campo NON scritto qui è, per costruzione, escluso da ogni
     * calendario (ICS e provider esterni).
     */
    public function toEventData(Appointment $appointment): CalendarEventData
    {
        $appointment->loadMissing(['animal', 'organization']);

        $start = $appointment->scheduled_at->copy();
        $end = $start->copy()->addMinutes(Appointment::SLOT_MINUTES);

        return new CalendarEventData(
            uid: $this->uid($appointment),
            summary: $this->summary($appointment),
            start: $start,
            end: $end,
            location: $this->location($appointment),
            description: $this->description($appointment),
            status: $this->status($appointment->status),
            appointmentId: $appointment->getKey(),
            // SEQUENCE monotòno = updated_at: a ogni modifica (conferma, spostamento,
            // annullo) cresce, così i client rigorosi accettano l'aggiornamento dello
            // stesso UID invece di ignorarlo. Fallback a 0 se updated_at è assente.
            sequence: (int) ($appointment->updated_at?->getTimestamp() ?? 0),
        );
    }

    /**
     * UID stabile e deterministico per appuntamento: "appointment-{id}@{dominio}".
     * Deve restare identico fra un fetch e l'altro perché i client usano l'UID per
     * riconoscere lo stesso evento e aggiornarlo (non duplicarlo).
     */
    private function uid(Appointment $appointment): string
    {
        return sprintf('appointment-%d@%s', $appointment->getKey(), $this->appDomain());
    }

    /**
     * SUMMARY localizzato "{tipo}: {animale}" — es. "Visita: Rex". Nessun dato
     * personale del richiedente.
     */
    private function summary(Appointment $appointment): string
    {
        return __('appointments.ics.summary', [
            'type' => $appointment->type->getLabel(),
            'animal' => $appointment->animal->name,
        ]);
    }

    /**
     * LOCATION = nome ente + città, MAI la via. Se la città manca, solo il nome.
     */
    private function location(Appointment $appointment): string
    {
        $organization = $appointment->organization;
        $city = $organization->city;

        if ($city !== null && $city !== '') {
            return $organization->name.', '.$city;
        }

        return $organization->name;
    }

    /**
     * DESCRIPTION minimale (tipo + animale), localizzata. NESSUN contatto del
     * richiedente, nessuna nota interna.
     */
    private function description(Appointment $appointment): string
    {
        return __('appointments.ics.description', [
            'type' => $appointment->type->getLabel(),
            'animal' => $appointment->animal->name,
        ]);
    }

    /**
     * Mappa lo stato di dominio sullo stato NEUTRO dell'evento (indipendente dal
     * dialetto del canale):
     *  - Requested  → tentative (non ancora confermato);
     *  - Confirmed  → confirmed;
     *  - Completed  → confirmed (incontro avvenuto: resta in calendario);
     *  - Cancelled  → cancelled (il client lo rimuove/barra);
     *  - NoShow     → confirmed (era programmato: non è una cancellazione).
     *
     * L'ICS rimappa a CONFIRMED/TENTATIVE/CANCELLED (RFC-5545 §3.8.1.11); Google e
     * CalDAV usano già gli stessi identificatori minuscoli.
     */
    private function status(AppointmentStatus $status): string
    {
        return match ($status) {
            AppointmentStatus::Requested => 'tentative',
            AppointmentStatus::Cancelled => 'cancelled',
            AppointmentStatus::Confirmed,
            AppointmentStatus::Completed,
            AppointmentStatus::NoShow => 'confirmed',
        };
    }

    /**
     * Dominio dell'app derivato da APP_URL, usato nel suffisso dell'UID. Fallback a
     * "arkvel" quando APP_URL non è parsabile (mantiene l'UID sempre ben formato).
     */
    private function appDomain(): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : 'arkvel';
    }
}
