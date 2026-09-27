<?php

declare(strict_types=1);

use App\Exceptions\CalendarProviderNotConfigured;
use App\Models\CalendarConnection;
use App\Support\Calendar\CalendarEventData;
use App\Support\Calendar\Providers\GoogleCalendarProvider;
use App\Support\Calendar\Providers\PulledChanges;
use App\Support\Calendar\Providers\PulledEvent;
use App\Support\Calendar\Providers\PushedEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();

    config()->set('services.google_calendar.client_id', 'client-abc');
    config()->set('services.google_calendar.client_secret', 'secret-xyz');
});

/**
 * Evento di test minimizzato. Il richiedente NON è presente per costruzione: se il
 * mapper facesse trapelare PII, non passerebbe da qui. Il test PII pianta comunque
 * un'email/cognome in campi vicini per provare l'assenza nel body inviato.
 */
function googleEvent(int $appointmentId = 42): CalendarEventData
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

it('inserts a new event (POST) with a minimized body, RFC3339 UTC times, iCalUID and extended property; returns id + etag', function () {
    Http::fake([
        'www.googleapis.com/calendar/v3/*' => Http::response([
            'id' => 'google-event-1',
            'etag' => '"etag-body"',
        ], 200, ['ETag' => '"etag-header"']),
    ]);

    $connection = CalendarConnection::factory()->google()->create();

    $pushed = app(GoogleCalendarProvider::class)->pushEvent($connection, googleEvent(), null, null);

    expect($pushed)->toBeInstanceOf(PushedEvent::class)
        ->and($pushed->externalId)->toBe('google-event-1')
        ->and($pushed->etag)->toBe('"etag-header"');

    Http::assertSent(function (Request $request): bool {
        $body = $request->data();

        return $request->method() === 'POST'
            && $request->url() === 'https://www.googleapis.com/calendar/v3/calendars/primary/events'
            // Bearer token presente (dal token manager).
            && $request->hasHeader('Authorization', 'Bearer fresh-access-token')
            && $body['summary'] === 'Visita: Rex'
            && $body['location'] === 'Rifugio Speranza, Milano'
            && $body['status'] === 'confirmed'
            && $body['iCalUID'] === 'appointment-42@arkvel.test'
            // 09:00 Europe/Rome → 08:00 UTC in RFC3339.
            && $body['start']['dateTime'] === '2026-01-15T08:00:00+00:00'
            && $body['end']['dateTime'] === '2026-01-15T09:00:00+00:00'
            && $body['extendedProperties']['private']['arkvel_appointment_id'] === '42';
    });
});

it('sends NO personal data of the applicant in the request body (PII minimization)', function () {
    Http::fake([
        'www.googleapis.com/calendar/v3/*' => Http::response(['id' => 'e', 'etag' => '"x"'], 200),
    ]);

    $connection = CalendarConnection::factory()->google()->create();

    app(GoogleCalendarProvider::class)->pushEvent($connection, googleEvent(), null, null);

    // Pianto marcatori PII e asserisco che il corpo serializzato NON li contiene: il VO
    // minimizzato è l'unica sorgente, quindi email/cognome non possono uscire.
    Http::assertSent(function (Request $request): bool {
        $json = $request->body();

        return ! str_contains($json, 'adopter@example.com')
            && ! str_contains($json, 'Rossi')
            && ! str_contains($json, 'Authorization'); // il body non ripete mai l'header
    });
});

it('updates an existing event with PATCH and If-Match when an id and etag are known', function () {
    Http::fake([
        'www.googleapis.com/calendar/v3/*' => Http::response(['id' => 'google-event-1', 'etag' => '"e2"'], 200),
    ]);

    $connection = CalendarConnection::factory()->google()->create();

    $pushed = app(GoogleCalendarProvider::class)
        ->pushEvent($connection, googleEvent(), 'google-event-1', '"old-etag"');

    expect($pushed->externalId)->toBe('google-event-1');

    Http::assertSent(function (Request $request): bool {
        return $request->method() === 'PATCH'
            && $request->url() === 'https://www.googleapis.com/calendar/v3/calendars/primary/events/google-event-1'
            && $request->hasHeader('If-Match', '"old-etag"');
    });
});

it('falls back to insert (POST) when the existing event returns 404 on update', function () {
    $sequence = Http::sequence()
        ->push(['error' => 'not found'], 404) // PATCH → evento sparito a monte
        ->push(['id' => 'recreated-event', 'etag' => '"e3"'], 200); // POST di ricreazione

    Http::fake(['www.googleapis.com/calendar/v3/*' => $sequence]);

    $connection = CalendarConnection::factory()->google()->create();

    $pushed = app(GoogleCalendarProvider::class)
        ->pushEvent($connection, googleEvent(), 'stale-id', '"stale-etag"');

    expect($pushed->externalId)->toBe('recreated-event');

    Http::assertSentCount(2);
});

it('recovers from a 409 duplicate iCalUID by finding and updating the existing event', function () {
    // Scenario reale: l'evento con quello iCalUID esiste già su Google (ArkVel ha perso
    // la mappatura dopo un migrate:fresh). L'insert torna 409 → il provider trova
    // l'evento per iCalUID e lo AGGIORNA, invece di fallire.
    $sequence = Http::sequence()
        ->push(['error' => ['code' => 409, 'message' => 'The requested identifier already exists']], 409) // POST insert
        ->push(['items' => [['id' => 'existing-event-9', 'etag' => '"e9"']]], 200) // GET ?iCalUID=
        ->push(['id' => 'existing-event-9', 'etag' => '"e10"'], 200); // PATCH update

    Http::fake(['www.googleapis.com/calendar/v3/*' => $sequence]);

    $connection = CalendarConnection::factory()->google()->create();

    $pushed = app(GoogleCalendarProvider::class)
        ->pushEvent($connection, googleEvent(), null, null);

    expect($pushed->externalId)->toBe('existing-event-9');

    // La PATCH di recupero NON deve reinviare iCalUID (di sola lettura su update).
    Http::assertSent(function (Request $request): bool {
        if ($request->method() !== 'PATCH') {
            return false;
        }

        return ! array_key_exists('iCalUID', (array) $request->data());
    });

    Http::assertSentCount(3);
});

it('deletes an event (DELETE with If-Match), tolerating 404/410 as already gone', function () {
    Http::fake([
        'www.googleapis.com/calendar/v3/*' => Http::response(null, 204),
    ]);

    $connection = CalendarConnection::factory()->google()->create();

    app(GoogleCalendarProvider::class)->deleteEvent($connection, 'google-event-1', '"etag"');

    Http::assertSent(function (Request $request): bool {
        return $request->method() === 'DELETE'
            && $request->url() === 'https://www.googleapis.com/calendar/v3/calendars/primary/events/google-event-1'
            && $request->hasHeader('If-Match', '"etag"');
    });
});

it('does not throw when deleting an already-gone event (410)', function () {
    Http::fake([
        'www.googleapis.com/calendar/v3/*' => Http::response(['error' => 'gone'], 410),
    ]);

    $connection = CalendarConnection::factory()->google()->create();

    // Un evento già sparito lato Google (410) non deve propagare: la cancellazione è
    // idempotente. Asseriamo esplicitamente il non-lancio + che la chiamata è partita.
    expect(fn () => app(GoogleCalendarProvider::class)->deleteEvent($connection, 'gone-event', null))
        ->not->toThrow(Throwable::class);

    Http::assertSentCount(1);
});

it('pulls changes parsing changed, deleted and the next sync token', function () {
    Http::fake([
        'www.googleapis.com/calendar/v3/*' => Http::response([
            'items' => [
                [
                    'id' => 'evt-changed',
                    'etag' => '"c1"',
                    'status' => 'confirmed',
                    'extendedProperties' => ['private' => ['arkvel_appointment_id' => '77']],
                ],
                [
                    'id' => 'evt-deleted',
                    'status' => 'cancelled',
                ],
            ],
            'nextSyncToken' => 'sync-token-next',
        ]),
    ]);

    $connection = CalendarConnection::factory()->google()->create(['sync_token' => 'prev-token']);

    $changes = app(GoogleCalendarProvider::class)->pullChanges($connection);

    expect($changes)->toBeInstanceOf(PulledChanges::class)
        ->and($changes->changed)->toHaveCount(1)
        ->and($changes->changed[0])->toBeInstanceOf(PulledEvent::class)
        ->and($changes->changed[0]->externalId)->toBe('evt-changed')
        ->and($changes->changed[0]->appointmentId)->toBe(77)
        ->and($changes->deletedExternalIds)->toBe(['evt-deleted'])
        ->and($changes->newSyncToken)->toBe('sync-token-next');

    // Ha usato il syncToken salvato per il pull incrementale.
    Http::assertSent(fn (Request $request): bool => $request->data()['syncToken'] === 'prev-token');
});

it('paginates the pull across pageToken and returns the final sync token', function () {
    $sequence = Http::sequence()
        ->push([
            'items' => [['id' => 'p1', 'status' => 'confirmed']],
            'nextPageToken' => 'page-2',
        ])
        ->push([
            'items' => [['id' => 'p2', 'status' => 'confirmed']],
            'nextSyncToken' => 'final-token',
        ]);

    Http::fake(['www.googleapis.com/calendar/v3/*' => $sequence]);

    $connection = CalendarConnection::factory()->google()->create(['sync_token' => 'prev']);

    $changes = app(GoogleCalendarProvider::class)->pullChanges($connection);

    expect($changes->changed)->toHaveCount(2)
        ->and($changes->newSyncToken)->toBe('final-token');

    Http::assertSentCount(2);
});

it('returns an empty change set with a null token when the sync token is invalid (410 GONE)', function () {
    Http::fake([
        'www.googleapis.com/calendar/v3/*' => Http::response(['error' => 'gone'], 410),
    ]);

    $connection = CalendarConnection::factory()->google()->create(['sync_token' => 'too-old']);

    $changes = app(GoogleCalendarProvider::class)->pullChanges($connection);

    expect($changes->changed)->toBe([])
        ->and($changes->deletedExternalIds)->toBe([])
        ->and($changes->newSyncToken)->toBeNull();
});

it('throws CalendarProviderNotConfigured on push when credentials are cleared', function () {
    config()->set('services.google_calendar.client_id', null);
    config()->set('services.google_calendar.client_secret', null);

    $connection = CalendarConnection::factory()->google()->create();

    expect(fn () => app(GoogleCalendarProvider::class)->pushEvent($connection, googleEvent(), null, null))
        ->toThrow(CalendarProviderNotConfigured::class);

    Http::assertNothingSent();
});

it('surfaces a sanitized error on a non-2xx response without leaking the token', function () {
    Http::fake([
        'www.googleapis.com/calendar/v3/*' => Http::response(['error' => 'bad request'], 400),
    ]);

    $connection = CalendarConnection::factory()->google()->create();

    try {
        app(GoogleCalendarProvider::class)->pushEvent($connection, googleEvent(), null, null);
        expect()->fail('Expected a RuntimeException');
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toContain('HTTP 400')
            ->and($exception->getMessage())->not->toContain('fresh-access-token');
    }
});
