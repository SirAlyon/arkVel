<?php

declare(strict_types=1);

namespace App\Support\Calendar\Providers;

use App\Enums\CalendarProvider;
use App\Exceptions\CalendarProviderNotConfigured;
use App\Models\CalendarConnection;
use App\Support\Calendar\CalendarEventData;
use App\Support\Calendar\Google\GoogleOAuth;
use App\Support\Calendar\Google\GoogleTokenManager;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Provider Google Calendar reale (Cal-5): implementa il contratto
 * {@see ExternalCalendarProvider} parlando con l'API Calendar v3 via Laravel {@see Http}
 * (NESSUNA SDK → interamente falsabile con `Http::fake()`).
 *
 * GDPR by design: consuma il {@see CalendarEventData} GIÀ minimizzato (mai il model
 * Appointment). Ciò che finisce su Google è SOLO summary/location/description/orari/stato
 * più l'`iCalUID` stabile e una extended property privata `arkvel_appointment_id` per la
 * riconciliazione (Cal-7). Nessun dato personale del richiedente lascia ArkVel: quel che
 * non è nel VO, per costruzione, non viene inviato.
 *
 * CONFIG-GATING: senza credenziali OAuth ogni operazione di rete solleva
 * {@see CalendarProviderNotConfigured} (via {@see GoogleOAuth::configured()}), così il
 * provider resta inerte finché l'installazione non è configurata.
 *
 * SICUREZZA DEI MESSAGGI: gli errori sollevati NON contengono mai l'header Authorization
 * né il token; il corpo d'errore di Google non contiene segreti, ma non lo logghiamo per
 * intero — solo status + snippet sanificato.
 *
 * Create-or-update IDEMPOTENTE come il resto della pipeline: `pushEvent` inserisce
 * (POST) quando non c'è un id, altrimenti aggiorna (PATCH con If-Match sull'etag). Se un
 * id salvato non esiste più su Google (404) ricrea l'evento (fallback a insert).
 */
final class GoogleCalendarProvider implements ExternalCalendarProvider
{
    private const string BASE_URL = 'https://www.googleapis.com/calendar/v3';

    /**
     * Calendario di default quando la connessione non ne specifica uno: il calendario
     * principale dell'account.
     */
    private const string DEFAULT_CALENDAR_ID = 'primary';

    /**
     * Chiave della extended property privata usata per ritrovare l'appuntamento ArkVel
     * nel pull di riconciliazione (Cal-7), senza dover interpretare l'UID.
     */
    public const string APPOINTMENT_PROPERTY = 'arkvel_appointment_id';

    public function __construct(
        private readonly GoogleTokenManager $tokens,
    ) {}

    public function provider(): CalendarProvider
    {
        return CalendarProvider::Google;
    }

    /**
     * Crea o aggiorna l'evento su Google. Inserisce (POST) se non c'è un id esistente;
     * altrimenti aggiorna (PATCH con If-Match quando l'etag è noto). Un 404 sull'update
     * (evento cancellato a monte) fa il fallback a insert.
     */
    public function pushEvent(
        CalendarConnection $connection,
        CalendarEventData $event,
        ?string $existingExternalId,
        ?string $existingEtag,
    ): PushedEvent {
        $this->ensureConfigured();

        $body = $this->toGoogleEvent($event);

        if ($existingExternalId === null) {
            return $this->insert($connection, $body);
        }

        $response = $this->request($connection)
            ->when(
                $existingEtag !== null && $existingEtag !== '',
                fn (PendingRequest $request): PendingRequest => $request->withHeaders(['If-Match' => $existingEtag]),
            )
            ->patch($this->eventUrl($connection, $existingExternalId), $body);

        // Evento sparito a monte: lo ricreiamo invece di fallire (self-healing).
        if ($response->status() === 404) {
            return $this->insert($connection, $body);
        }

        return $this->pushedFrom($this->ensureSuccessful($response));
    }

    /**
     * Cancella l'evento su Google. 404/410 (già assente) sono tollerati: la
     * cancellazione è idempotente.
     */
    public function deleteEvent(
        CalendarConnection $connection,
        string $externalId,
        ?string $etag,
    ): void {
        $this->ensureConfigured();

        $response = $this->request($connection)
            ->when(
                $etag !== null && $etag !== '',
                fn (PendingRequest $request): PendingRequest => $request->withHeaders(['If-Match' => $etag]),
            )
            ->delete($this->eventUrl($connection, $externalId));

        // Già cancellato / mai esistito: nulla da fare.
        if (in_array($response->status(), [404, 410], true)) {
            return;
        }

        $this->ensureSuccessful($response);
    }

    /**
     * Pull incrementale (Cal-7 lo consuma). Usa il `syncToken` salvato sulla connessione
     * quando presente (per ottenere SOLO le modifiche dall'ultimo sync), altrimenti una
     * finestra temporale limitata (`timeMin`). Impagina su `pageToken` e raccoglie:
     *  - `changed`: eventi creati/modificati (id, arkvel_appointment_id, status, etag);
     *  - `deletedExternalIds`: eventi con status=cancelled.
     * L'ultima pagina porta il `nextSyncToken` da persistere per il prossimo giro.
     *
     * Un 410 GONE (syncToken invalido/troppo vecchio) → insieme VUOTO con token null,
     * così il chiamante (Cal-7) potrà fare un resync completo.
     */
    public function pullChanges(CalendarConnection $connection): PulledChanges
    {
        $this->ensureConfigured();

        $changed = [];
        $deleted = [];
        $pageToken = null;
        $newSyncToken = null;

        do {
            $query = $this->pullQuery($connection, $pageToken);

            $response = $this->request($connection)->get($this->eventsUrl($connection), $query);

            // syncToken invalido: il chiamante deve ripartire da zero.
            if ($response->status() === 410) {
                return new PulledChanges([], [], null);
            }

            $data = $this->ensureSuccessful($response)->json();
            $items = is_array($data['items'] ?? null) ? $data['items'] : [];

            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $externalId = is_string($item['id'] ?? null) ? $item['id'] : null;

                if ($externalId === null) {
                    continue;
                }

                if (($item['status'] ?? null) === 'cancelled') {
                    $deleted[] = $externalId;

                    continue;
                }

                $changed[] = [
                    'external_id' => $externalId,
                    'etag' => is_string($item['etag'] ?? null) ? $item['etag'] : null,
                    'status' => is_string($item['status'] ?? null) ? $item['status'] : null,
                    'appointment_id' => $this->appointmentIdFrom($item),
                ];
            }

            $pageToken = is_string($data['nextPageToken'] ?? null) ? $data['nextPageToken'] : null;
            $newSyncToken = is_string($data['nextSyncToken'] ?? null) ? $data['nextSyncToken'] : $newSyncToken;
        } while ($pageToken !== null);

        return new PulledChanges($changed, $deleted, $newSyncToken);
    }

    /**
     * Insert (POST) di un nuovo evento.
     *
     * @param  array<string, mixed>  $body
     */
    private function insert(CalendarConnection $connection, array $body): PushedEvent
    {
        $response = $this->request($connection)->post($this->eventsUrl($connection), $body);

        return $this->pushedFrom($this->ensureSuccessful($response));
    }

    /**
     * Mappa il VO minimizzato nel corpo evento dell'API Google. È l'UNICO punto in cui si
     * decide cosa esce verso Google: campi minimi + iCalUID stabile + extended property
     * privata per la riconciliazione. Nessun dato personale del richiedente.
     *
     * @return array<string, mixed>
     */
    private function toGoogleEvent(CalendarEventData $event): array
    {
        $body = [
            'summary' => $event->summary,
            'start' => ['dateTime' => $this->rfc3339Utc($event->start)],
            'end' => ['dateTime' => $this->rfc3339Utc($event->end)],
            // Stato NEUTRO già allineato al dialetto Google (confirmed/tentative/cancelled).
            'status' => $event->status,
            // UID stabile: consente a Google/altri client di deduplicare per lo stesso evento.
            'iCalUID' => $event->uid,
            // Property PRIVATA per ritrovare l'appuntamento in fase di pull (Cal-7).
            'extendedProperties' => [
                'private' => [
                    self::APPOINTMENT_PROPERTY => (string) $event->appointmentId,
                ],
            ],
        ];

        if ($event->location !== null && $event->location !== '') {
            $body['location'] = $event->location;
        }

        if ($event->description !== null && $event->description !== '') {
            $body['description'] = $event->description;
        }

        return $body;
    }

    /**
     * Richiesta base autenticata verso l'API Calendar: Bearer token della connessione
     * (rinnovato al volo dal token manager) + Accept JSON.
     */
    private function request(CalendarConnection $connection): PendingRequest
    {
        return Http::withToken($this->tokens->accessTokenFor($connection))
            ->acceptJson();
    }

    /**
     * Costruisce {@see PushedEvent} dalla risposta di insert/update: id evento + etag
     * (per l'update condizionale successivo). L'etag arriva nell'header ETag o nel corpo.
     */
    private function pushedFrom(Response $response): PushedEvent
    {
        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        $id = is_string($data['id'] ?? null) ? $data['id'] : '';

        $etag = $response->header('ETag');

        if ($etag === '' && is_string($data['etag'] ?? null)) {
            $etag = $data['etag'];
        }

        return new PushedEvent($id, $etag !== '' ? $etag : null);
    }

    /**
     * Estrae l'id appuntamento ArkVel dalle extended properties private dell'evento
     * remoto (chiave {@see APPOINTMENT_PROPERTY}). Null se assente/non numerico.
     *
     * @param  array<string, mixed>  $item
     */
    private function appointmentIdFrom(array $item): ?int
    {
        $extended = $item['extendedProperties'] ?? null;

        if (! is_array($extended)) {
            return null;
        }

        $private = $extended['private'] ?? null;

        if (! is_array($private)) {
            return null;
        }

        $value = $private[self::APPOINTMENT_PROPERTY] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * Parametri di query per il pull: dall'ultimo syncToken (solo modifiche) oppure una
     * finestra temporale limitata; sempre showDeleted per intercettare le cancellazioni.
     *
     * @return array<string, mixed>
     */
    private function pullQuery(CalendarConnection $connection, ?string $pageToken): array
    {
        $query = [
            'showDeleted' => 'true',
            'maxResults' => 250,
        ];

        if ($pageToken !== null) {
            $query['pageToken'] = $pageToken;
        }

        $syncToken = $connection->sync_token;

        if (is_string($syncToken) && $syncToken !== '') {
            $query['syncToken'] = $syncToken;
        } else {
            // Primo pull (nessun token): limitiamo la finestra per non scaricare l'intero
            // storico del calendario.
            $query['timeMin'] = Carbon::now()->subMonth()->toRfc3339String();
        }

        return $query;
    }

    /**
     * URL della collezione eventi del calendario di destinazione.
     */
    private function eventsUrl(CalendarConnection $connection): string
    {
        return self::BASE_URL.'/calendars/'.rawurlencode($this->calendarId($connection)).'/events';
    }

    /**
     * URL del singolo evento.
     */
    private function eventUrl(CalendarConnection $connection, string $eventId): string
    {
        return $this->eventsUrl($connection).'/'.rawurlencode($eventId);
    }

    /**
     * Calendario di destinazione: quello dichiarato dalla connessione o 'primary'.
     */
    private function calendarId(CalendarConnection $connection): string
    {
        $calendarId = $connection->external_calendar_id;

        return is_string($calendarId) && $calendarId !== '' ? $calendarId : self::DEFAULT_CALENDAR_ID;
    }

    /**
     * Formatta un istante come RFC-3339 in UTC (il formato accettato da Google per
     * start/end.dateTime), indipendentemente dal timezone d'ingresso.
     */
    private function rfc3339Utc(Carbon $moment): string
    {
        return $moment->copy()->utc()->toRfc3339String();
    }

    /**
     * Garantisce una risposta 2xx o solleva un errore SANIFICATO (status + snippet del
     * corpo, MAI il token né l'header Authorization).
     */
    private function ensureSuccessful(Response $response): Response
    {
        if ($response->successful()) {
            return $response;
        }

        throw new RuntimeException(sprintf(
            'Google Calendar API error (HTTP %d): %s',
            $response->status(),
            Str::of($response->body())->replaceMatches('/\s+/', ' ')->limit(200)->value(),
        ));
    }

    /**
     * @throws CalendarProviderNotConfigured
     */
    private function ensureConfigured(): void
    {
        if (! GoogleOAuth::configured()) {
            throw CalendarProviderNotConfigured::google();
        }
    }
}
