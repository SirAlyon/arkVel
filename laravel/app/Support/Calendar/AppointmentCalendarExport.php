<?php

declare(strict_types=1);

namespace App\Support\Calendar;

use App\Models\Appointment;
use App\Models\Organization;
use Illuminate\Support\Carbon;

/**
 * Serializza gli appuntamenti in testo iCalendar (RFC-5545).
 *
 * La MINIMIZZAZIONE dei dati personali (GDPR by design) NON vive più qui: è stata
 * estratta in {@see AppointmentEventMapper}, che produce un {@see CalendarEventData}
 * già minimizzato condiviso con i provider esterni (Google/CalDAV). Questa classe è
 * ora una PROIEZIONE del VO nel formato iCalendar: decide come rendere l'evento in
 * .ics (STATUS RFC, PRODID, finestra del feed), non QUALI dati escono.
 *
 * È comunque il punto UNICO d'ingresso all'ICS: sia il feed d'ente in sottoscrizione
 * ({@see feed()}) sia il download del singolo evento ({@see single()}) — e l'allegato
 * .ics delle notifiche — passano di qui.
 *
 * COSA FINISCE nell'evento (deciso da {@see AppointmentEventMapper}, non qui): summary
 * "{tipo}: {animale}", location nome ente + città, description minimale, UID stabile,
 * STATUS mappato. MAI: email/telefono/cognome del richiedente, note interne, via.
 *
 * Servizio puro (nessuno stato, nessuna dipendenza I/O oltre al mapper iniettato):
 * riceve model già caricati e restituisce stringhe. La risoluzione tenant-scoped e
 * l'autorizzazione vivono nel controller/nelle pagine, non qui.
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

    public function __construct(
        private readonly AppointmentEventMapper $mapper,
    ) {}

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
     * Serializza un appuntamento come VEVENT: mappa l'appuntamento nel VO minimizzato
     * (unica fonte di verità dei dati che escono) e lo proietta nel formato iCal.
     */
    private function writeEvent(IcsWriter $writer, Appointment $appointment): void
    {
        $event = $this->mapper->toEventData($appointment);

        $writer->event(
            uid: $event->uid,
            start: $event->start,
            end: $event->end,
            summary: $event->summary,
            status: IcsStatus::fromNeutral($event->status),
            location: $event->location,
            description: $event->description,
            // SEQUENCE monotòno = updated_at (dal VO): a ogni modifica cresce, così i
            // client rigorosi accettano l'aggiornamento dello stesso UID.
            sequence: $event->sequence,
        );
    }

    /**
     * PRODID del calendario: identifica il software che ha generato l'ICS.
     */
    private function productIdentifier(): string
    {
        return sprintf('-//ArkVel//Appointments//%s', app()->getLocale());
    }
}
