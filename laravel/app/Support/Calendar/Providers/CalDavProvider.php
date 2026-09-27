<?php

declare(strict_types=1);

namespace App\Support\Calendar\Providers;

use App\Enums\CalendarProvider;
use App\Models\CalendarConnection;
use App\Support\Calendar\CalDav\CalDavClient;
use App\Support\Calendar\CalendarEventData;
use App\Support\Calendar\IcsStatus;
use App\Support\Calendar\IcsWriter;

/**
 * Provider CalDAV reale (Cal-6): implementa il contratto {@see ExternalCalendarProvider}
 * parlando con una collezione CalDAV standard via {@see CalDavClient} (Laravel Http +
 * HTTP Basic, NESSUNA SDK → interamente falsabile con `Http::fake()`).
 *
 * Compatibile con Proton (via Bridge), Fastmail, Nextcloud, iCloud e ogni server CalDAV
 * conforme: le credenziali (url della collezione + username + app-password) vivono nel
 * campo cifrato `credentials` della connessione, l'auth Basic è applicata a OGNI richiesta
 * dal client. L'app-password non affiora mai in UI, log o errori.
 *
 * GDPR by design: consuma il {@see CalendarEventData} GIÀ minimizzato (mai il model
 * Appointment) e lo serializza in un singolo VEVENT via {@see IcsWriter} — lo stesso
 * writer del feed ICS, con la stessa mappatura di stato ({@see IcsStatus}). Ciò che
 * finisce sul server è SOLO summary/location/description/orari/stato + l'UID stabile:
 * nessun dato personale del richiedente lascia ArkVel (quel che non è nel VO, per
 * costruzione, non viene inviato).
 *
 * NIENTE CONFIG-GATING: a differenza di Google, CalDAV è per-connessione (ogni ente
 * inserisce il proprio server), quindi non c'è configurazione globale da controllare. Il
 * provider è sempre attivo; se le credenziali sono errate, è il singolo push a fallire e
 * la connessione passa a Error (gestito dal job).
 *
 * MODELLO DELLE RISORSE: `external_calendar_id` è l'URL della COLLEZIONE; l'evento vive
 * all'href `{collectionUrl}{uid}.ics`, che è anche l'id di mappatura restituito in
 * {@see PushedEvent::$externalId}. L'UID è ricavabile dall'href (`{uid}.ics`), utile alla
 * riconciliazione (Cal-7).
 */
final class CalDavProvider implements ExternalCalendarProvider
{
    /**
     * PRODID del VCALENDAR generato per il push (identifica il software sorgente).
     */
    private const string PRODUCT_IDENTIFIER = '-//ArkVel//Appointments CalDAV//EN';

    public function __construct(
        private readonly CalDavClient $client,
    ) {}

    public function provider(): CalendarProvider
    {
        return CalendarProvider::CalDav;
    }

    /**
     * Crea o aggiorna l'evento sul server CalDAV con un PUT dell'ICS all'href stabile.
     *
     * - CREAZIONE ($existingExternalId null): PUT con `If-None-Match: *` (riesce solo se
     *   la risorsa non esiste) all'href derivato dall'UID.
     * - AGGIORNAMENTO: PUT con `If-Match: <etag>` all'href già noto (idempotente,
     *   condizionale). Se l'href salvato non c'è più, il PUT ricrea comunque la risorsa.
     *
     * Se il server non restituisce l'ETag sul PUT (comportamento comune), lo si recupera
     * con un HEAD best-effort: null è accettabile (il prossimo update ometterà If-Match).
     */
    public function pushEvent(
        CalendarConnection $connection,
        CalendarEventData $event,
        ?string $existingExternalId,
        ?string $existingEtag,
    ): PushedEvent {
        // In aggiornamento riusa l'href salvato; in creazione lo deriva dall'UID così è
        // stabile e deterministico ({collectionUrl}{uid}.ics).
        $href = $existingExternalId ?? $this->hrefFor($connection, $event);

        $ics = $this->buildIcs($event);

        $isCreate = $existingExternalId === null;

        $etag = $this->client->putEvent(
            $connection,
            $href,
            $ics,
            $isCreate ? null : $existingEtag,
            ifNoneMatch: $isCreate,
        );

        // Il PUT può non restituire l'ETag: lo leggiamo best-effort (null è tollerato).
        if ($etag === null) {
            $etag = $this->client->fetchEtag($connection, $href);
        }

        return new PushedEvent($href, $etag);
    }

    /**
     * Cancella l'evento (DELETE dell'href). `If-Match` quando l'etag è noto. 404/410
     * (già assente) sono tollerati dal client: la cancellazione è idempotente.
     */
    public function deleteEvent(
        CalendarConnection $connection,
        string $externalId,
        ?string $etag,
    ): void {
        $this->client->deleteEvent($connection, $externalId, $etag);
    }

    /**
     * Pull incrementale via sync-collection (Cal-7 lo consuma). Mappa il risultato del
     * client in {@see PulledChanges}:
     *  - `changed`: gli href delle risorse create/modificate (l'href È l'external id; l'UID
     *    è ricavabile come `{uid}.ics` per la riconciliazione);
     *  - `deletedExternalIds`: gli href delle risorse rimosse;
     *  - `newSyncToken`: il nuovo sync-token da persistere.
     *
     * Se il server non supporta sync-collection, il client degrada a insieme vuoto col
     * token preservato: qui lo propaghiamo tale e quale.
     */
    public function pullChanges(CalendarConnection $connection): PulledChanges
    {
        $result = $this->client->syncCollection($connection);

        $changed = [];

        foreach ($result['changed'] as $entry) {
            $changed[] = [
                'external_id' => $entry['href'],
                'etag' => $entry['etag'],
                // L'UID è embeddato nell'href come "{uid}.ics": lo estraiamo per la
                // riconciliazione (Cal-7), null se l'href non segue la convenzione.
                'uid' => $this->uidFromHref($entry['href']),
            ];
        }

        return new PulledChanges($changed, $result['deleted'], $result['syncToken']);
    }

    /**
     * Costruisce il VCALENDAR con l'UNICO VEVENT dell'evento minimizzato, riusando lo
     * stesso {@see IcsWriter} del feed ICS. Lo stato neutro è proiettato sui valori
     * iCalendar via {@see IcsStatus} (stessa mappatura dell'export → nessuna divergenza).
     */
    private function buildIcs(CalendarEventData $event): string
    {
        return (new IcsWriter(self::PRODUCT_IDENTIFIER))
            ->beginCalendar()
            ->event(
                uid: $event->uid,
                start: $event->start,
                end: $event->end,
                summary: $event->summary,
                status: IcsStatus::fromNeutral($event->status),
                location: $event->location,
                description: $event->description,
                sequence: $event->sequence,
            )
            ->endCalendar()
            ->toString();
    }

    /**
     * Href stabile del singolo evento: `{collectionUrl}{uid}.ics`. La collezione è
     * `external_calendar_id`; garantiamo un unico slash di giunzione.
     */
    private function hrefFor(CalendarConnection $connection, CalendarEventData $event): string
    {
        $collection = is_string($connection->external_calendar_id) ? $connection->external_calendar_id : '';

        return rtrim($collection, '/').'/'.rawurlencode($event->uid).'.ics';
    }

    /**
     * Ricava l'UID dell'evento dall'href (`.../{uid}.ics`), invertendo l'encoding del
     * segmento. Null se l'href non termina con `.ics`.
     */
    private function uidFromHref(string $href): ?string
    {
        $basename = basename($href);

        if (! str_ends_with($basename, '.ics')) {
            return null;
        }

        return rawurldecode(substr($basename, 0, -4));
    }
}
