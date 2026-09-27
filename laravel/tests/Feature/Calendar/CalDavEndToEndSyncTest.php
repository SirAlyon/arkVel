<?php

declare(strict_types=1);

use App\Jobs\SyncAppointmentToCalendarsJob;
use App\Models\Animal;
use App\Models\Appointment;
use App\Models\AppointmentExternalEvent;
use App\Models\CalendarConnection;
use App\Models\Organization;
use App\Support\Calendar\AppointmentEventMapper;
use App\Support\Calendar\CalendarProviderRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\assertDatabaseHas;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
});

/**
 * End-to-end della pipeline Cal-4 verso il provider CalDAV reale: un upsert di un
 * appuntamento su un ente con una connessione CalDAV (faked) deve emettere il PUT e
 * registrare la mappatura appointment ↔ evento remoto (href).
 *
 * Guidiamo il job come farebbe l'observer (afterCommit sotto RefreshDatabase non spara
 * in modo affidabile), ma usiamo il REGISTRY REALE risolto dal container — quindi il
 * CalDavProvider registrato nel service provider, non un doppio: è la stessa pipeline che
 * gira in produzione.
 */
it('pushes an appointment to a CalDAV connection via the Cal-4 pipeline and records the href mapping', function () {
    Http::fake([
        'caldav.example.test/*' => Http::response('', 201, ['ETag' => '"remote-etag"']),
    ]);

    $org = Organization::factory()->create();
    $connection = CalendarConnection::factory()->calDav()->active()->forOrganization($org)->create();

    // Bus::fake per catturare (non eseguire) il dispatch dell'observer alla creazione,
    // così controlliamo noi quando gira il job — con il registry REALE.
    Bus::fake();
    $animal = Animal::factory()->forOrganization($org)->create(['name' => 'Rex']);
    $appointment = Appointment::factory()->confirmed()->forAnimal($animal)->create();

    $mapper = app(AppointmentEventMapper::class);

    (new SyncAppointmentToCalendarsJob($appointment->getKey(), SyncAppointmentToCalendarsJob::OP_UPSERT))->handle(
        app(CalendarProviderRegistry::class),
        $mapper,
    );

    // L'href è {collectionUrl}{uid}.ics, con l'UID prodotto dal mapper (dominio derivato
    // dall'installazione): lo ricaviamo dal mapper per non fissare il dominio nel test.
    $uid = $mapper->toEventData($appointment->fresh(['animal', 'organization']))->uid;
    $expectedHref = rtrim((string) $connection->external_calendar_id, '/')
        .'/'.rawurlencode($uid).'.ics';

    // La mappatura persiste l'href come external_event_id e l'etag remoto.
    assertDatabaseHas('appointment_external_events', [
        'appointment_id' => $appointment->getKey(),
        'calendar_connection_id' => $connection->getKey(),
        'external_event_id' => $expectedHref,
    ]);

    $mapping = AppointmentExternalEvent::query()->firstOrFail();
    expect($mapping->etag)->toBe('"remote-etag"')
        ->and($mapping->last_pushed_at)->not->toBeNull();

    // È partito un PUT verso l'href, con Basic auth e corpo iCalendar (senza PII: il
    // summary è "{tipo}: {animale}", il richiedente non compare).
    Http::assertSent(function (Request $request) use ($expectedHref): bool {
        return $request->method() === 'PUT'
            && $request->url() === $expectedHref
            && $request->hasHeader('If-None-Match', '*')
            && str_contains($request->body(), 'BEGIN:VEVENT')
            && str_contains($request->body(), 'Rex');
    });
});
