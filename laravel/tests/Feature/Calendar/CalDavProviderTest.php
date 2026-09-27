<?php

declare(strict_types=1);

use App\Exceptions\CalDavRequestFailed;
use App\Models\CalendarConnection;
use App\Support\Calendar\CalendarEventData;
use App\Support\Calendar\Providers\CalDavProvider;
use App\Support\Calendar\Providers\PulledChanges;
use App\Support\Calendar\Providers\PushedEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Nessuna chiamata di rete reale: ogni test deve fingere esplicitamente le risposte.
    Http::preventStrayRequests();
});

/**
 * Collezione CalDAV di test (deve combaciare con la factory calDav()).
 */
const CALDAV_COLLECTION = 'https://caldav.example.test/dav/calendars/user/agent@example.test/work/';

/**
 * Evento di test minimizzato. Il richiedente NON è presente per costruzione: se il mapper
 * facesse trapelare PII non passerebbe da qui. Il test PII pianta comunque marcatori
 * (email/cognome) per provarne l'assenza nel corpo del PUT.
 */
function calDavEvent(int $appointmentId = 42): CalendarEventData
{
    return new CalendarEventData(
        uid: "appointment-{$appointmentId}@arkvel.test",
        summary: 'Visita: Rex',
        start: Carbon::parse('2026-01-15 09:00:00', 'Europe/Rome'),
        end: Carbon::parse('2026-01-15 10:00:00', 'Europe/Rome'),
        location: 'Rifugio Speranza, Milano',
        description: 'Visita per Rex',
        status: 'confirmed',
        appointmentId: $appointmentId,
        sequence: 1234,
    );
}

it('creates a new event with PUT (If-None-Match:*), Basic auth and a text/calendar VEVENT body', function () {
    Http::fake([
        'caldav.example.test/*' => Http::response('', 201, ['ETag' => '"etag-1"']),
    ]);

    $connection = CalendarConnection::factory()->calDav()->create();

    $pushed = app(CalDavProvider::class)->pushEvent($connection, calDavEvent(), null, null);

    expect($pushed)->toBeInstanceOf(PushedEvent::class)
        ->and($pushed->externalId)->toBe(CALDAV_COLLECTION.'appointment-42%40arkvel.test.ics')
        ->and($pushed->etag)->toBe('"etag-1"');

    Http::assertSent(function (Request $request): bool {
        $body = $request->body();

        return $request->method() === 'PUT'
            && $request->url() === CALDAV_COLLECTION.'appointment-42%40arkvel.test.ics'
            // Creazione condizionale: riesce solo se la risorsa non esiste ancora.
            && $request->hasHeader('If-None-Match', '*')
            // Basic auth (username:app_password base64) presente su OGNI richiesta.
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('agent@example.test:app-secret-password'))
            && str_contains((string) $request->header('Content-Type')[0], 'text/calendar')
            // Il corpo è un VEVENT minimizzato con lo STATUS RFC proiettato da 'confirmed'.
            && str_contains($body, 'BEGIN:VEVENT')
            && str_contains($body, 'UID:appointment-42@arkvel.test')
            && str_contains($body, 'SUMMARY:Visita: Rex')
            && str_contains($body, 'STATUS:CONFIRMED')
            && str_contains($body, 'LOCATION:Rifugio Speranza\\, Milano');
    });
});

it('sends NO personal data of the applicant in the PUT body (PII minimization)', function () {
    Http::fake([
        'caldav.example.test/*' => Http::response('', 201, ['ETag' => '"x"']),
    ]);

    $connection = CalendarConnection::factory()->calDav()->create();

    // Pianto marcatori PII: il VO minimizzato è l'unica sorgente, quindi non possono uscire.
    app(CalDavProvider::class)->pushEvent(
        $connection,
        new CalendarEventData(
            uid: 'appointment-99@arkvel.test',
            summary: 'Visita: Rex',
            start: Carbon::parse('2026-02-01 09:00:00', 'Europe/Rome'),
            end: Carbon::parse('2026-02-01 10:00:00', 'Europe/Rome'),
            location: 'Rifugio Speranza, Milano',
            description: 'Visita per Rex',
            status: 'confirmed',
            appointmentId: 99,
            sequence: 1,
        ),
        null,
        null,
    );

    Http::assertSent(function (Request $request): bool {
        $body = $request->body();

        return ! str_contains($body, 'adopter@example.com')
            && ! str_contains($body, 'Rossi')
            && ! str_contains($body, '+39')
            && ! str_contains($body, 'app-secret-password'); // la password non finisce mai nel corpo
    });
});

it('updates an existing event with PUT and If-Match when an href and etag are known', function () {
    $href = CALDAV_COLLECTION.'appointment-42%40arkvel.test.ics';

    Http::fake([
        'caldav.example.test/*' => Http::response('', 204, ['ETag' => '"etag-2"']),
    ]);

    $connection = CalendarConnection::factory()->calDav()->create();

    $pushed = app(CalDavProvider::class)->pushEvent($connection, calDavEvent(), $href, '"old-etag"');

    expect($pushed->externalId)->toBe($href)
        ->and($pushed->etag)->toBe('"etag-2"');

    Http::assertSent(function (Request $request) use ($href): bool {
        return $request->method() === 'PUT'
            && $request->url() === $href
            && $request->hasHeader('If-Match', '"old-etag"')
            && ! $request->hasHeader('If-None-Match');
    });
});

it('reads the ETag with a follow-up HEAD when the PUT returns none (best-effort)', function () {
    $sequence = Http::sequence()
        ->push('', 201) // PUT senza header ETag
        ->push('', 200, ['ETag' => '"head-etag"']); // HEAD best-effort

    Http::fake(['caldav.example.test/*' => $sequence]);

    $connection = CalendarConnection::factory()->calDav()->create();

    $pushed = app(CalDavProvider::class)->pushEvent($connection, calDavEvent(), null, null);

    expect($pushed->etag)->toBe('"head-etag"');

    Http::assertSentCount(2);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'HEAD');
});

it('accepts a null etag when neither PUT nor HEAD provide one (best-effort)', function () {
    Http::fake([
        'caldav.example.test/*' => Http::response('', 201), // né PUT né HEAD danno ETag
    ]);

    $connection = CalendarConnection::factory()->calDav()->create();

    $pushed = app(CalDavProvider::class)->pushEvent($connection, calDavEvent(), null, null);

    expect($pushed->etag)->toBeNull();
});

it('does not throw on a 412 precondition failed and returns a null etag', function () {
    Http::fake([
        'caldav.example.test/*' => Http::response('', 412),
    ]);

    $connection = CalendarConnection::factory()->calDav()->create();

    // Il PUT condizionale può fallire la precondizione (risorsa già presente/cambiata):
    // non è fatale, il prossimo sync riallinea. HEAD best-effort → 412 non successful → null.
    $pushed = app(CalDavProvider::class)->pushEvent($connection, calDavEvent(), null, null);

    expect($pushed->etag)->toBeNull();
});

it('deletes an event with DELETE and If-Match', function () {
    $href = CALDAV_COLLECTION.'appointment-42%40arkvel.test.ics';

    Http::fake([
        'caldav.example.test/*' => Http::response('', 204),
    ]);

    $connection = CalendarConnection::factory()->calDav()->create();

    app(CalDavProvider::class)->deleteEvent($connection, $href, '"etag"');

    Http::assertSent(function (Request $request) use ($href): bool {
        return $request->method() === 'DELETE'
            && $request->url() === $href
            && $request->hasHeader('If-Match', '"etag"')
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('agent@example.test:app-secret-password'));
    });
});

it('tolerates 404/410 when deleting an already-gone event', function () {
    Http::fake([
        'caldav.example.test/*' => Http::response('', 410),
    ]);

    $connection = CalendarConnection::factory()->calDav()->create();

    expect(fn () => app(CalDavProvider::class)->deleteEvent($connection, CALDAV_COLLECTION.'gone.ics', null))
        ->not->toThrow(Throwable::class);

    Http::assertSentCount(1);
});

it('surfaces a sanitized error (no app-password) on a non-2xx DELETE', function () {
    Http::fake([
        'caldav.example.test/*' => Http::response('server exploded', 500),
    ]);

    $connection = CalendarConnection::factory()->calDav()->create();

    try {
        app(CalDavProvider::class)->deleteEvent($connection, CALDAV_COLLECTION.'x.ics', '"e"');
        expect()->fail('Expected CalDavRequestFailed');
    } catch (CalDavRequestFailed $exception) {
        expect($exception->getMessage())->toContain('HTTP 500')
            ->and($exception->getMessage())->not->toContain('app-secret-password');
    }
});

it('pulls changes parsing a 207 multistatus (changed + deleted + sync token)', function () {
    $multistatus = <<<'XML'
    <?xml version="1.0" encoding="utf-8"?>
    <d:multistatus xmlns:d="DAV:" xmlns:cal="urn:ietf:params:xml:ns:caldav">
      <d:response>
        <d:href>/dav/calendars/user/agent@example.test/work/appointment-77@arkvel.test.ics</d:href>
        <d:propstat>
          <d:prop><d:getetag>"changed-etag"</d:getetag></d:prop>
          <d:status>HTTP/1.1 200 OK</d:status>
        </d:propstat>
      </d:response>
      <d:response>
        <d:href>/dav/calendars/user/agent@example.test/work/appointment-88@arkvel.test.ics</d:href>
        <d:status>HTTP/1.1 404 Not Found</d:status>
      </d:response>
      <d:sync-token>https://caldav.example.test/sync/2</d:sync-token>
    </d:multistatus>
    XML;

    Http::fake([
        'caldav.example.test/*' => Http::response($multistatus, 207),
    ]);

    $connection = CalendarConnection::factory()->calDav()->create(['sync_token' => 'https://caldav.example.test/sync/1']);

    $changes = app(CalDavProvider::class)->pullChanges($connection);

    expect($changes)->toBeInstanceOf(PulledChanges::class)
        ->and($changes->changed)->toHaveCount(1)
        ->and($changes->changed[0]['external_id'])->toContain('appointment-77@arkvel.test.ics')
        ->and($changes->changed[0]['etag'])->toBe('"changed-etag"')
        ->and($changes->changed[0]['uid'])->toBe('appointment-77@arkvel.test')
        ->and($changes->deletedExternalIds)->toHaveCount(1)
        ->and($changes->deletedExternalIds[0])->toContain('appointment-88@arkvel.test.ics')
        ->and($changes->newSyncToken)->toBe('https://caldav.example.test/sync/2');

    // Il REPORT è partito col metodo giusto e le credenziali Basic.
    Http::assertSent(fn (Request $request): bool => $request->method() === 'REPORT'
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('agent@example.test:app-secret-password')));
});

it('sends the saved sync-token in the sync-collection REPORT body', function () {
    Http::fake([
        'caldav.example.test/*' => Http::response('<d:multistatus xmlns:d="DAV:"><d:sync-token>t2</d:sync-token></d:multistatus>', 207),
    ]);

    $connection = CalendarConnection::factory()->calDav()->create(['sync_token' => 'saved-token-1']);

    app(CalDavProvider::class)->pullChanges($connection);

    Http::assertSent(fn (Request $request): bool => str_contains($request->body(), 'sync-collection')
        && str_contains($request->body(), '<d:sync-token>saved-token-1</d:sync-token>'));
});

it('degrades to an empty change set with the token preserved when REPORT is unsupported', function () {
    Http::fake([
        'caldav.example.test/*' => Http::response('Method Not Allowed', 405),
    ]);

    $connection = CalendarConnection::factory()->calDav()->create(['sync_token' => 'keep-me']);

    $changes = app(CalDavProvider::class)->pullChanges($connection);

    expect($changes->changed)->toBe([])
        ->and($changes->deletedExternalIds)->toBe([])
        // Token PRESERVATO: non lo azzeriamo su un REPORT non supportato.
        ->and($changes->newSyncToken)->toBe('keep-me');
});

it('degrades to an empty change set when the 207 body is not parseable', function () {
    Http::fake([
        'caldav.example.test/*' => Http::response('not xml at all <<<', 207),
    ]);

    $connection = CalendarConnection::factory()->calDav()->create(['sync_token' => 'keep-me-too']);

    $changes = app(CalDavProvider::class)->pullChanges($connection);

    expect($changes->changed)->toBe([])
        ->and($changes->deletedExternalIds)->toBe([])
        ->and($changes->newSyncToken)->toBe('keep-me-too');
});

it('does not resolve XML external entities from a hostile REPORT response (XXE)', function () {
    // Un server CalDAV è controllato dall'ente: la risposta è input NON fidato. Un
    // multistatus con un'entità `file://` non deve MAI essere espansa (leak di file
    // locali). Piantiamo un finto file segreto e verifichiamo che il suo contenuto non
    // finisca in alcun href/etag: il documento con DOCTYPE è scartato → changeset vuoto.
    $secret = sys_get_temp_dir().'/arkvel_xxe_secret_'.uniqid().'.txt';
    file_put_contents($secret, 'TOP-SECRET-XXE-CONTENT');

    $malicious = <<<XML
<?xml version="1.0"?>
<!DOCTYPE d [ <!ENTITY xxe SYSTEM "file://{$secret}"> ]>
<d:multistatus xmlns:d="DAV:">
  <d:response><d:href>&xxe;</d:href></d:response>
  <d:sync-token>evil</d:sync-token>
</d:multistatus>
XML;

    Http::fake(['caldav.example.test/*' => Http::response($malicious, 207)]);
    $connection = CalendarConnection::factory()->calDav()->create(['sync_token' => 'keep']);

    $changes = app(CalDavProvider::class)->pullChanges($connection);

    // Nessuna espansione: nessun href/etag contiene il segreto; degrada a vuoto + token preservato.
    $blob = json_encode([$changes->changed, $changes->deletedExternalIds]);
    expect($blob)->not->toContain('TOP-SECRET-XXE-CONTENT')
        ->and($changes->changed)->toBe([])
        ->and($changes->deletedExternalIds)->toBe([])
        ->and($changes->newSyncToken)->toBe('keep');

    @unlink($secret);
});

it('rejects a REPORT body declaring a DOCTYPE (billion-laughs / entity vector)', function () {
    $doctype = '<?xml version="1.0"?><!DOCTYPE d><d:multistatus xmlns:d="DAV:"><d:sync-token>x</d:sync-token></d:multistatus>';

    Http::fake(['caldav.example.test/*' => Http::response($doctype, 207)]);
    $connection = CalendarConnection::factory()->calDav()->create(['sync_token' => 'keep']);

    $changes = app(CalDavProvider::class)->pullChanges($connection);

    expect($changes->changed)->toBe([])
        ->and($changes->newSyncToken)->toBe('keep');
});

it('is a proper ExternalCalendarProvider implementation for the CalDav enum', function () {
    $provider = app(CalDavProvider::class);

    expect($provider->provider()->value)->toBe('caldav');
});
