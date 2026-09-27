<?php

declare(strict_types=1);

namespace App\Support\Calendar\CalDav;

use App\Exceptions\CalDavRequestFailed;
use App\Models\CalendarConnection;
use App\Support\Calendar\Providers\CalDavProvider;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Client CalDAV di basso livello, scritto a mano sul client {@see Http} di Laravel —
 * NESSUNA SDK, così ogni richiesta è interamente falsabile con `Http::fake()` nei test.
 *
 * Compatibile con qualsiasi server CalDAV standard: Proton (via Bridge), Fastmail,
 * Nextcloud, iCloud. Il {@see CalDavProvider} lo usa per il
 * push (PUT/DELETE) e per il pull di riconciliazione (REPORT sync-collection, Cal-7).
 *
 * AUTENTICAZIONE: HTTP Basic (username + app-password) su OGNI richiesta, letta dal
 * campo cifrato `credentials` della connessione. L'header Authorization è impostato da
 * {@see PendingRequest::withBasicAuth()} e NON viene mai loggato né incluso negli errori:
 * l'app-password non affiora mai in UI, log o messaggi d'eccezione.
 *
 * MODELLO DELLE RISORSE: `external_calendar_id` della connessione è l'URL della
 * COLLEZIONE (es. `https://caldav.fastmail.com/dav/calendars/user/x@y/z/`). Un singolo
 * evento vive all'href `{collectionUrl}{uid}.ics`; quell'href è l'id di mappatura
 * (`external_event_id`) usato dalla pipeline di push.
 *
 * DEGRADAZIONE: le operazioni di scrittura tollerano le condizioni idempotenti tipiche
 * di CalDAV (412 sul PUT condizionale, 404/410 sul DELETE). Il REPORT sync-collection —
 * non supportato da tutti i server — degrada a un insieme di modifiche VUOTO col token
 * preservato, invece di sollevare: la riconciliazione (Cal-7) resta best-effort.
 */
final class CalDavClient
{
    /**
     * Namespace XML del core WebDAV (RFC-4918): usato per href, propstat, status e per
     * l'elemento <sync-token> della sync-collection (RFC-6578).
     */
    private const string NS_DAV = 'DAV:';

    /**
     * Namespace XML CalDAV (RFC-4791): usato per <calendar-multiget> e per l'elemento
     * <calendar-data> che trasporta il corpo iCalendar (VEVENT) nelle risposte.
     */
    private const string NS_CALDAV = 'urn:ietf:params:xml:ns:caldav';

    /**
     * Carica (create-or-update) un evento all'href assoluto dato.
     *
     * - `Content-Type: text/calendar; charset=utf-8` come impone CalDAV per un VEVENT.
     * - In CREAZIONE (`$ifNoneMatch = true`) invia `If-None-Match: *`: il PUT riesce
     *   solo se la risorsa NON esiste già (evita di sovrascrivere un evento omonimo).
     * - In AGGIORNAMENTO invia `If-Match: <etag>` quando l'etag è noto: il PUT riesce
     *   solo se la risorsa non è cambiata a monte (update condizionale, ottimistico).
     *
     * Ritorna l'ETag della risposta (header `ETag`) quando il server lo fornisce sul PUT;
     * molti server lo omettono → null (il provider lo recupera con un GET/HEAD best-effort).
     * Un 412 (precondition failed) è gestito con grazia: nessuna eccezione, ETag null —
     * il chiamante rileggerà lo stato al prossimo giro.
     */
    public function putEvent(
        CalendarConnection $connection,
        string $href,
        string $ics,
        ?string $ifMatchEtag,
        bool $ifNoneMatch = false,
    ): ?string {
        $request = $this->request($connection)
            ->withBody($ics, 'text/calendar; charset=utf-8');

        if ($ifNoneMatch) {
            $request = $request->withHeaders(['If-None-Match' => '*']);
        } elseif ($ifMatchEtag !== null && $ifMatchEtag !== '') {
            $request = $request->withHeaders(['If-Match' => $ifMatchEtag]);
        }

        $response = $request->send('PUT', $href);

        // Precondizione fallita: la risorsa esiste già (create) o è cambiata a monte
        // (update). Non è un errore fatale: il prossimo sync riallineerà lo stato.
        if ($response->status() === 412) {
            return null;
        }

        $this->ensureSuccessful('PUT', $response);

        return $this->etagFrom($response);
    }

    /**
     * Legge l'ETag corrente della risorsa all'href (HEAD, o GET in fallback). Usato dal
     * provider quando il PUT non ha restituito l'ETag: best-effort, null se assente o se
     * la risorsa non esiste (404/410) — mai un'eccezione.
     */
    public function fetchEtag(CalendarConnection $connection, string $href): ?string
    {
        $response = $this->request($connection)->send('HEAD', $href);

        if (! $response->successful()) {
            return null;
        }

        return $this->etagFrom($response);
    }

    /**
     * Cancella l'evento all'href. `If-Match: <etag>` quando l'etag è noto (cancellazione
     * condizionale). 404/410 (risorsa già assente) sono TOLLERATI: la cancellazione è
     * idempotente. Ogni altro status non-2xx solleva {@see CalDavRequestFailed}.
     */
    public function deleteEvent(CalendarConnection $connection, string $href, ?string $ifMatchEtag): void
    {
        $request = $this->request($connection);

        if ($ifMatchEtag !== null && $ifMatchEtag !== '') {
            $request = $request->withHeaders(['If-Match' => $ifMatchEtag]);
        }

        $response = $request->delete($href);

        // Già cancellato / mai esistito: nulla da fare.
        if (in_array($response->status(), [404, 410], true)) {
            return;
        }

        $this->ensureSuccessful('DELETE', $response);
    }

    /**
     * Verifica leggera che la collezione all'URL sia raggiungibile con le credenziali
     * date e sia effettivamente un calendario. Usata dal flusso di connessione PRIMA di
     * persistere le credenziali.
     *
     * Emette un PROPFIND `Depth: 0`: un server CalDAV risponde con 207 (multistatus).
     * Consideriamo la collezione valida se la risposta è di successo (2xx/207) O se
     * annuncia il supporto CalDAV nell'header `DAV: ...calendar-access`. Qualsiasi errore
     * di rete/HTTP → false (nessuna connessione creata, avviso all'utente).
     */
    public function verify(string $url, string $user, string $pass): bool
    {
        try {
            $response = Http::withBasicAuth($user, $pass)
                ->withHeaders(['Depth' => '0'])
                ->withBody($this->propfindBody(), 'application/xml; charset=utf-8')
                ->send('PROPFIND', $url);
        } catch (\Throwable) {
            return false;
        }

        // 207 Multi-Status è la risposta canonica di un PROPFIND su una collezione.
        if ($response->status() === 207 || $response->successful()) {
            return true;
        }

        // Alcuni server rispondono a un OPTIONS/PROPFIND annunciando le classi DAV
        // supportate: la presenza di "calendar-access" conferma che è un calendario.
        $davHeader = $response->header('DAV');

        return str_contains(strtolower($davHeader), 'calendar-access');
    }

    /**
     * Pull incrementale via REPORT `sync-collection` (RFC-6578) — consumato da Cal-7.
     *
     * Invia il `sync-token` salvato sulla connessione (o una sync INIZIALE quando null,
     * che il server risolve restituendo lo stato corrente + un primo token). Parsa il 207
     * multistatus in modo DIFENSIVO (i namespace variano) e ritorna:
     *   - `changed`:  [['href' => ..., 'etag' => ...], ...] delle risorse create/modificate;
     *   - `deleted`:  ['href', ...] delle risorse rimosse (status 404 nel <response>);
     *   - `syncToken`: il nuovo <sync-token> da persistere per il giro successivo.
     *
     * Un REPORT non supportato/fallito NON solleva: degrada a insieme VUOTO col token
     * PRESERVATO, così la sincronizzazione bidirezionale resta opzionale e non blocca il
     * push (che è la funzione principale).
     *
     * @return array{changed: list<array{href: string, etag: ?string}>, deleted: list<string>, syncToken: ?string}
     */
    public function syncCollection(CalendarConnection $connection): array
    {
        $collectionUrl = $this->collectionUrl($connection);
        $currentToken = is_string($connection->sync_token) ? $connection->sync_token : null;

        $empty = ['changed' => [], 'deleted' => [], 'syncToken' => $currentToken];

        try {
            $response = $this->request($connection)
                ->withHeaders(['Depth' => '1'])
                ->withBody($this->syncCollectionBody($currentToken), 'application/xml; charset=utf-8')
                ->send('REPORT', $collectionUrl);
        } catch (\Throwable) {
            return $empty;
        }

        // 207 è l'unica risposta utile; qualsiasi altra cosa (405 Method Not Allowed,
        // 400, 5xx) significa "sync-collection non supportata/fallita" → degrada.
        if ($response->status() !== 207) {
            return $empty;
        }

        return $this->parseSyncCollection($response->body(), $currentToken);
    }

    /**
     * Recupera in UN SOLO REPORT `calendar-multiget` (RFC-4791) il corpo iCalendar delle
     * risorse agli href dati — consumato dal pull di riconciliazione (Cal-7) per leggere
     * DTSTART/STATUS/UID degli eventi cambiati, che il sync-collection (solo href+etag) non
     * porta. Un singolo round-trip invece di un GET per href.
     *
     * Parsa il 207 multistatus in modo DIFENSIVO (loadXml XXE-safe) estraendo per ogni
     * <response> l'href e il <calendar-data>, poi ricava dal VEVENT:
     *  - `start`: {@see Carbon} UTC da DTSTART (con TZID/UTC/floating), null se non parsabile;
     *  - `status`: stato NEUTRO ('confirmed'|'tentative'|'cancelled') da STATUS, default 'confirmed';
     *  - `uid`: UID del VEVENT (per ricavare l'appuntamento ArkVel).
     *
     * DEGRADAZIONE: input vuoto, server che non supporta il multiget (status ≠ 207) o corpo
     * non parsabile → mappa VUOTA, MAI un'eccezione. Il chiamante emetterà eventi con start
     * null (solo cancellazioni/uid riconciliano): la bidirezionalità resta best-effort.
     *
     * @param  list<string>  $hrefs
     * @return array<string, array{start: ?Carbon, status: string, uid: ?string}>
     */
    public function multiget(CalendarConnection $connection, array $hrefs): array
    {
        if ($hrefs === []) {
            return [];
        }

        $collectionUrl = $this->collectionUrl($connection);

        try {
            $response = $this->request($connection)
                ->withHeaders(['Depth' => '1'])
                ->withBody($this->multigetBody($hrefs), 'application/xml; charset=utf-8')
                ->send('REPORT', $collectionUrl);
        } catch (\Throwable) {
            return [];
        }

        if ($response->status() !== 207) {
            return [];
        }

        return $this->parseMultiget($response->body());
    }

    /**
     * Richiesta base autenticata verso il server CalDAV: HTTP Basic (username +
     * app-password dal campo cifrato `credentials`). L'header Authorization è impostato
     * da withBasicAuth e non viene mai loggato né esposto.
     */
    private function request(CalendarConnection $connection): PendingRequest
    {
        $credentials = is_array($connection->credentials) ? $connection->credentials : [];

        $username = is_string($credentials['username'] ?? null) ? $credentials['username'] : '';
        $password = is_string($credentials['app_password'] ?? null) ? $credentials['app_password'] : '';

        return Http::withBasicAuth($username, $password);
    }

    /**
     * URL della collezione (calendario) di destinazione: `external_calendar_id`.
     */
    private function collectionUrl(CalendarConnection $connection): string
    {
        $url = $connection->external_calendar_id;

        return is_string($url) ? $url : '';
    }

    /**
     * Estrae l'ETag dalla risposta (header `ETag`); null se assente.
     */
    private function etagFrom(Response $response): ?string
    {
        $etag = $response->header('ETag');

        return $etag !== '' ? $etag : null;
    }

    /**
     * Corpo XML del PROPFIND di verifica: chiede la resourcetype (per riconoscere una
     * collezione calendario) — payload minimo e innocuo.
     */
    private function propfindBody(): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>'
            .'<d:propfind xmlns:d="DAV:">'
            .'<d:prop><d:resourcetype/><d:displayname/></d:prop>'
            .'</d:propfind>';
    }

    /**
     * Corpo XML del REPORT sync-collection (RFC-6578). Con token vuoto il server esegue
     * una sync INIZIALE (stato corrente completo). Chiediamo getetag per ogni risorsa,
     * così il chiamante può mappare href→etag senza un GET aggiuntivo.
     */
    private function syncCollectionBody(?string $syncToken): string
    {
        return '<?xml version="1.0" encoding="utf-8"?>'
            .'<d:sync-collection xmlns:d="DAV:">'
            .'<d:sync-token>'.$this->escapeXml($syncToken ?? '').'</d:sync-token>'
            .'<d:sync-level>1</d:sync-level>'
            .'<d:prop><d:getetag/></d:prop>'
            .'</d:sync-collection>';
    }

    /**
     * Parsa il 207 multistatus di una sync-collection in modo difensivo (namespace
     * variabili). Ogni <response> porta un <href> e uno <status>: status 200 → risorsa
     * cambiata (con il suo getetag), status 404 → risorsa cancellata. Il <sync-token> di
     * primo livello è il nuovo token da persistere.
     *
     * @return array{changed: list<array{href: string, etag: ?string}>, deleted: list<string>, syncToken: ?string}
     */
    private function parseSyncCollection(string $xml, ?string $currentToken): array
    {
        $document = $this->loadXml($xml);

        if ($document === null) {
            return ['changed' => [], 'deleted' => [], 'syncToken' => $currentToken];
        }

        $changed = [];
        $deleted = [];

        foreach ($document->getElementsByTagNameNS(self::NS_DAV, 'response') as $node) {
            $href = $this->firstTagValue($node, 'href');

            if ($href === null || $href === '') {
                continue;
            }

            // Uno status 404 a livello di <response> marca la risorsa come cancellata.
            $responseStatus = $this->firstTagValue($node, 'status');

            if ($responseStatus !== null && str_contains($responseStatus, '404')) {
                $deleted[] = $href;

                continue;
            }

            $changed[] = [
                'href' => $href,
                'etag' => $this->firstTagValue($node, 'getetag'),
            ];
        }

        $newToken = $this->firstTagValueFromRoot($document, 'sync-token') ?? $currentToken;

        return ['changed' => $changed, 'deleted' => $deleted, 'syncToken' => $newToken];
    }

    /**
     * Corpo XML del REPORT calendar-multiget (RFC-4791): chiede getetag + calendar-data
     * per l'elenco di href dato. Ogni href è escapato (può contenere caratteri speciali).
     *
     * @param  list<string>  $hrefs
     */
    private function multigetBody(array $hrefs): string
    {
        $hrefElements = '';

        foreach ($hrefs as $href) {
            $hrefElements .= '<d:href>'.$this->escapeXml($href).'</d:href>';
        }

        return '<?xml version="1.0" encoding="utf-8"?>'
            .'<cal:calendar-multiget xmlns:d="DAV:" xmlns:cal="'.self::NS_CALDAV.'">'
            .'<d:prop><d:getetag/><cal:calendar-data/></d:prop>'
            .$hrefElements
            .'</cal:calendar-multiget>';
    }

    /**
     * Parsa il 207 di un calendar-multiget: per ogni <response> mappa href → dettagli
     * (start UTC, status neutro, uid) letti dal VEVENT nel <calendar-data>. Difensivo:
     * XML non parsabile o senza calendar-data → salta la voce.
     *
     * @return array<string, array{start: ?Carbon, status: string, uid: ?string}>
     */
    private function parseMultiget(string $xml): array
    {
        $document = $this->loadXml($xml);

        if ($document === null) {
            return [];
        }

        $result = [];

        foreach ($document->getElementsByTagNameNS(self::NS_DAV, 'response') as $node) {
            $href = $this->firstTagValue($node, 'href');

            if ($href === null || $href === '') {
                continue;
            }

            $calendarData = $node->getElementsByTagNameNS(self::NS_CALDAV, 'calendar-data')->item(0);

            if (! $calendarData instanceof \DOMNode) {
                continue;
            }

            $result[$href] = $this->parseVEvent($calendarData->textContent);
        }

        return $result;
    }

    /**
     * Parse MINIMALE di un corpo iCalendar (VEVENT): legge DTSTART, STATUS e UID riga per
     * riga (l'ICS è un formato a righe `NAME[;params]:value`). Non è un parser iCalendar
     * completo: bastano i tre campi che la riconciliazione usa.
     *
     * @return array{start: ?Carbon, status: string, uid: ?string}
     */
    private function parseVEvent(string $ics): array
    {
        $start = null;
        $status = 'confirmed';
        $uid = null;

        // Unfolding: le righe iCalendar lunghe si spezzano con CRLF + spazio/tab iniziale.
        $unfolded = (string) preg_replace('/\r\n[ \t]/', '', str_replace("\n", "\r\n", str_replace("\r\n", "\n", $ics)));

        foreach (preg_split('/\r\n|\n/', $unfolded) ?: [] as $line) {
            if ($line === '') {
                continue;
            }

            $colon = strpos($line, ':');

            if ($colon === false) {
                continue;
            }

            $left = substr($line, 0, $colon);
            $value = substr($line, $colon + 1);
            // Il nome della proprietà è prima dell'eventuale `;params` (es. DTSTART;TZID=…).
            $name = strtoupper(explode(';', $left, 2)[0]);

            if ($name === 'DTSTART') {
                $start = $this->parseDateTime($value, $left);
            } elseif ($name === 'STATUS') {
                $status = $this->neutralStatus($value);
            } elseif ($name === 'UID') {
                $uid = trim($value);
            }
        }

        return ['start' => $start, 'status' => $status, 'uid' => $uid];
    }

    /**
     * Parsa un valore DTSTART iCalendar in {@see Carbon} UTC. Gestisce le tre forme:
     *  - UTC (`20260115T080000Z`);
     *  - con TZID (`DTSTART;TZID=Europe/Rome:20260115T090000`);
     *  - floating/locale (senza suffisso, interpretato nel timezone dell'app → UTC).
     * Null quando il valore è vuoto o non parsabile (es. all-day `VALUE=DATE`): senza
     * un'ora precisa non riprogrammiamo.
     */
    private function parseDateTime(string $value, string $left): ?Carbon
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $timezone = $this->tzidFrom($left);

        try {
            if (str_ends_with($value, 'Z')) {
                return Carbon::createFromFormat('Ymd\THis\Z', $value, 'UTC')?->utc();
            }

            $parsed = Carbon::createFromFormat('Ymd\THis', $value, $timezone);

            return $parsed instanceof Carbon ? $parsed->utc() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Estrae il TZID dai parametri della proprietà (`DTSTART;TZID=Europe/Rome`). Assente →
     * timezone dell'app (interpretazione del floating time coerente col resto del dominio).
     */
    private function tzidFrom(string $left): string
    {
        if (preg_match('/TZID=([^;:]+)/i', $left, $matches) === 1) {
            return $matches[1];
        }

        return config()->string('app.timezone', 'UTC');
    }

    /**
     * Proietta lo STATUS iCalendar (CONFIRMED/TENTATIVE/CANCELLED) sul dialetto NEUTRO.
     * Qualsiasi valore inatteso ricade su 'confirmed' (evento presente e non annullato).
     */
    private function neutralStatus(string $value): string
    {
        return match (strtoupper(trim($value))) {
            'CANCELLED' => 'cancelled',
            'TENTATIVE' => 'tentative',
            default => 'confirmed',
        };
    }

    /**
     * Carica una stringa XML in un DOMDocument in modo SICURO contro XXE. Il server
     * CalDAV è controllato dall'ente (URL arbitrario), quindi la risposta è input non
     * fidato:
     *  - NIENTE LIBXML_NOENT: la sostituzione delle entità è ciò che consente a un
     *    server ostile di leggere file locali (`file://`) o fare SSRF; la lasciamo
     *    DISATTIVA (default) — nessun valore legittimo del multistatus dipende da
     *    entità;
     *  - LIBXML_NONET blocca comunque l'accesso di rete durante il parsing;
     *  - qualunque DOCTYPE fa scartare il documento (nessun sync-collection legittimo
     *    ne dichiara uno): chiude anche il vettore "billion laughs".
     * Null se vuoto/non parsabile/con DOCTYPE → il chiamante degrada a changeset vuoto.
     */
    private function loadXml(string $xml): ?\DOMDocument
    {
        if (trim($xml) === '') {
            return null;
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $document = new \DOMDocument;

            $ok = $document->loadXML($xml, LIBXML_NONET);

            // Rifiuta i documenti con DOCTYPE: un multistatus CalDAV legittimo non ne ha,
            // e la sua presenza è il preludio a un attacco a entità (XXE / billion laughs).
            if (! $ok || $document->doctype !== null) {
                return null;
            }

            return $document;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /**
     * Primo valore testuale del tag DAV: dato DENTRO un nodo (namespace-aware).
     */
    private function firstTagValue(\DOMElement $node, string $tag): ?string
    {
        $found = $node->getElementsByTagNameNS(self::NS_DAV, $tag)->item(0);

        return $found instanceof \DOMNode ? trim($found->textContent) : null;
    }

    /**
     * Primo valore testuale del tag DAV: a livello di DOCUMENTO (per il sync-token).
     */
    private function firstTagValueFromRoot(\DOMDocument $document, string $tag): ?string
    {
        $found = $document->getElementsByTagNameNS(self::NS_DAV, $tag)->item(0);

        if (! $found instanceof \DOMNode) {
            return null;
        }

        $value = trim($found->textContent);

        return $value !== '' ? $value : null;
    }

    /**
     * Escaping XML minimo per il valore del sync-token (può contenere caratteri speciali).
     */
    private function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * Garantisce una risposta 2xx o solleva un errore SANIFICATO (metodo + status +
     * snippet del corpo). MAI l'header Authorization né l'app-password.
     */
    private function ensureSuccessful(string $method, Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        throw CalDavRequestFailed::from($method, $response);
    }
}
