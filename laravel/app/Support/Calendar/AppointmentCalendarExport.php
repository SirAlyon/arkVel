<?php

declare(strict_types=1);

namespace App\Support\Calendar;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Organization;
use Illuminate\Support\Carbon;

/**
 * Mappa gli appuntamenti in testo iCalendar (RFC-5545), con MINIMIZZAZIONE dei dati
 * personali (GDPR by design).
 *
 * È il punto UNICO in cui un appuntamento diventa un evento di calendario: sia il
 * feed d'ente in sottoscrizione ({@see feed()}) sia il download del singolo evento
 * ({@see single()}) — e l'allegato .ics delle notifiche — passano di qui, così la
 * politica di minimizzazione è definita una volta sola e non può divergere.
 *
 * COSA FINISCE nell'evento (e cosa NO):
 *  - SUMMARY = "{tipo}: {animale}" (localizzato) — es. "Visita: Rex".
 *  - LOCATION = nome ente + città (MAI la via/indirizzo civico).
 *  - DESCRIPTION = tipo + animale, minimale.
 *  - UID = "appointment-{id}@{dominio app}" STABILE e deterministico: i client
 *    deduplicano/aggiornano invece di duplicare a ogni fetch del feed.
 *  - STATUS = mappato da AppointmentStatus (CONFIRMED/TENTATIVE/CANCELLED).
 *  - DTSTART = scheduled_at in UTC; DTEND = +SLOT_MINUTES (durata di dominio).
 *
 * MAI presenti: email, telefono, cognome del richiedente; note interne; indirizzo
 * civico dell'ente. Il richiedente non è nominato affatto: chi si iscrive al feed è
 * lo staff dell'ente, che identifica l'incontro da tipo+animale+orario.
 *
 * Servizio puro (nessuno stato, nessuna dipendenza I/O): riceve model già caricati e
 * restituisce stringhe. La risoluzione tenant-scoped e l'autorizzazione vivono nel
 * controller/nelle pagine, non qui.
 */
final class AppointmentCalendarExport
{
    /**
     * Finestra del feed: da 30 giorni fa a 365 giorni avanti. Il passato recente
     * resta visibile (utile in agenda), ma non trasciniamo storico illimitato — meno
     * dati esposti, feed più snello.
     */
    private const int WINDOW_PAST_DAYS = 30;

    private const int WINDOW_FUTURE_DAYS = 365;

    /**
     * VCALENDAR degli appuntamenti dell'ente nella finestra [now-30g, now+365g].
     *
     * ISOLAMENTO: filtra esplicitamente per organization_id dell'ente passato —
     * nessun appuntamento di altri enti può finire nel feed. Gli ANNULLATI sono
     * INCLUSI con STATUS:CANCELLED (non omessi): così un client già iscritto vede
     * sparire/barrare l'evento invece di trovarselo "fantasma" in calendario. Gli
     * altri stati terminali (Completed/NoShow) restano CONFIRMED: sono incontri
     * realmente avvenuti/programmati, non cancellazioni.
     */
    public function feed(Organization $organization): string
    {
        $from = Carbon::now()->subDays(self::WINDOW_PAST_DAYS);
        $to = Carbon::now()->addDays(self::WINDOW_FUTURE_DAYS);

        $appointments = Appointment::query()
            ->where('organization_id', $organization->getKey())
            ->whereBetween('scheduled_at', [$from, $to])
            ->with(['animal', 'organization'])
            ->orderBy('scheduled_at')
            ->get();

        $writer = (new IcsWriter($this->productIdentifier()))
            ->beginCalendar($organization->name);

        foreach ($appointments as $appointment) {
            $this->writeEvent($writer, $appointment);
        }

        return $writer->endCalendar()->toString();
    }

    /**
     * VCALENDAR con il SOLO evento dell'appuntamento dato — per il download .ics e
     * per l'allegato delle notifiche di conferma/spostamento. Stessa politica di
     * minimizzazione del feed.
     */
    public function single(Appointment $appointment): string
    {
        $appointment->loadMissing(['animal', 'organization']);

        $writer = (new IcsWriter($this->productIdentifier()))
            ->beginCalendar($appointment->organization->name);

        $this->writeEvent($writer, $appointment);

        return $writer->endCalendar()->toString();
    }

    /**
     * Serializza un appuntamento come VEVENT minimizzato. Punto UNICO di verità del
     * mapping: qualunque campo NON scritto qui è, per costruzione, escluso dall'ICS.
     */
    private function writeEvent(IcsWriter $writer, Appointment $appointment): void
    {
        $start = $appointment->scheduled_at->copy();
        $end = $start->copy()->addMinutes(Appointment::SLOT_MINUTES);

        $writer->event(
            uid: $this->uid($appointment),
            start: $start,
            end: $end,
            summary: $this->summary($appointment),
            status: $this->status($appointment->status),
            location: $this->location($appointment),
            description: $this->description($appointment),
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
     * Mappa lo stato di dominio sullo STATUS iCalendar (RFC-5545 §3.8.1.11):
     *  - Requested  → TENTATIVE (non ancora confermato);
     *  - Confirmed  → CONFIRMED;
     *  - Completed  → CONFIRMED (incontro avvenuto: resta in calendario);
     *  - Cancelled  → CANCELLED (il client lo rimuove/barra);
     *  - NoShow     → CONFIRMED (era programmato: non è una cancellazione).
     */
    private function status(AppointmentStatus $status): string
    {
        return match ($status) {
            AppointmentStatus::Requested => 'TENTATIVE',
            AppointmentStatus::Cancelled => 'CANCELLED',
            AppointmentStatus::Confirmed,
            AppointmentStatus::Completed,
            AppointmentStatus::NoShow => 'CONFIRMED',
        };
    }

    /**
     * PRODID del calendario: identifica il software che ha generato l'ICS.
     */
    private function productIdentifier(): string
    {
        return sprintf('-//ArkVel//Appointments//%s', app()->getLocale());
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
