<?php

declare(strict_types=1);

use App\Enums\CalendarConnectionStatus;
use App\Enums\CalendarProvider;
use App\Jobs\SyncAppointmentToCalendarsJob;
use App\Models\Animal;
use App\Models\Appointment;
use App\Models\AppointmentExternalEvent;
use App\Models\CalendarConnection;
use App\Models\Organization;
use App\Support\Calendar\AppointmentEventMapper;
use App\Support\Calendar\CalendarEventData;
use App\Support\Calendar\CalendarProviderRegistry;
use App\Support\Calendar\Providers\ExternalCalendarProvider;
use App\Support\Calendar\Providers\LogCalendarProvider;
use App\Support\Calendar\Providers\PulledChanges;
use App\Support\Calendar\Providers\PushedEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

uses(RefreshDatabase::class);

/**
 * Appuntamento per un ente dato, esercitando la pipeline DIRETTAMENTE via {@see runSync()}.
 *
 * Con la coda `sync` dei test, l'observer eseguirebbe il job già alla creazione (verso
 * il provider reale) sporcando lo scenario: fingiamo il Bus per catturare — non
 * eseguire — quel dispatch, così ogni test controlla esplicitamente quando e con quale
 * registry gira il job.
 */
function jobAppointment(Organization $organization): Appointment
{
    Bus::fake();

    $animal = Animal::factory()->forOrganization($organization)->create(['name' => 'Rex']);

    return Appointment::factory()->confirmed()->forAnimal($animal)->create();
}

/**
 * Esegue il job in-process (senza coda) per l'appuntamento e l'operazione dati.
 *
 * @param  list<array{connection_id: int, external_id: string, etag: string|null}>  $deletions
 */
function runSync(Appointment $appointment, string $operation, array $deletions = []): void
{
    (new SyncAppointmentToCalendarsJob($appointment->getKey(), $operation, $deletions))->handle(
        app(CalendarProviderRegistry::class),
        app(AppointmentEventMapper::class),
    );
}

it('pushes an appointment to an active connection and records the mapping', function () {
    $org = Organization::factory()->create();
    $connection = CalendarConnection::factory()->log()->active()->forOrganization($org)->create();
    $appointment = jobAppointment($org);

    runSync($appointment, SyncAppointmentToCalendarsJob::OP_UPSERT);

    assertDatabaseHas('appointment_external_events', [
        'appointment_id' => $appointment->getKey(),
        'calendar_connection_id' => $connection->getKey(),
        'external_event_id' => 'log-'.$appointment->getKey(),
    ]);

    $mapping = AppointmentExternalEvent::query()->firstOrFail();
    expect($mapping->external_event_id)->toBe('log-'.$appointment->getKey())
        ->and($mapping->etag)->not->toBeNull()
        ->and($mapping->last_pushed_at)->not->toBeNull();

    // La connessione resta Active con last_synced_at aggiornato.
    $connection->refresh();
    expect($connection->status)->toBe(CalendarConnectionStatus::Active)
        ->and($connection->last_synced_at)->not->toBeNull();
});

it('is idempotent: a second upsert updates the same mapping without duplicating', function () {
    $org = Organization::factory()->create();
    $connection = CalendarConnection::factory()->log()->active()->forOrganization($org)->create();
    $appointment = jobAppointment($org);

    runSync($appointment, SyncAppointmentToCalendarsJob::OP_UPSERT);
    $firstId = AppointmentExternalEvent::query()->firstOrFail()->getKey();

    runSync($appointment, SyncAppointmentToCalendarsJob::OP_UPSERT);

    // Una sola riga, con lo stesso id: aggiornata, non duplicata.
    assertDatabaseCount('appointment_external_events', 1);
    expect(AppointmentExternalEvent::query()->firstOrFail()->getKey())->toBe($firstId);
});

it('calls the provider delete for each ref carried in the delete payload', function () {
    $org = Organization::factory()->create();
    $connection = CalendarConnection::factory()->log()->active()->forOrganization($org)->create();
    $appointment = jobAppointment($org);

    // Provider spia che REGISTRA gli external id cancellati (il delete non dipende più
    // dalla mappatura DB: usa i riferimenti nel payload, che il cascade avrebbe rimosso).
    $deleted = [];
    $spy = new class($deleted) implements ExternalCalendarProvider
    {
        /** @param array<int, string> $deleted */
        public function __construct(public array &$deleted) {}

        public function provider(): CalendarProvider
        {
            return CalendarProvider::Log;
        }

        public function pushEvent(CalendarConnection $connection, CalendarEventData $event, ?string $existingExternalId, ?string $existingEtag): PushedEvent
        {
            return new PushedEvent('spy-'.$event->appointmentId, 'spy-etag');
        }

        public function deleteEvent(CalendarConnection $connection, string $externalId, ?string $etag): void
        {
            $this->deleted[] = $externalId;
        }

        public function pullChanges(CalendarConnection $connection): PulledChanges
        {
            return PulledChanges::empty();
        }
    };

    $registry = new CalendarProviderRegistry;
    $registry->register($spy);
    app()->instance(CalendarProviderRegistry::class, $registry);

    runSync($appointment, SyncAppointmentToCalendarsJob::OP_DELETE, [
        ['connection_id' => $connection->getKey(), 'external_id' => 'log-42', 'etag' => 'e1'],
    ]);

    expect($spy->deleted)->toBe(['log-42']);
    expect($connection->refresh()->status)->toBe(CalendarConnectionStatus::Active);
});

it('snapshots the mappings on appointment deletion and dispatches a delete with their refs', function () {
    $org = Organization::factory()->create();
    $connection = CalendarConnection::factory()->log()->active()->forOrganization($org)->create();
    $appointment = jobAppointment($org); // Bus::fake() attivo da qui

    // Crea la mappatura remota (upsert diretto: il dispatch dell'observer è fake).
    runSync($appointment, SyncAppointmentToCalendarsJob::OP_UPSERT);
    $mapping = AppointmentExternalEvent::query()->firstOrFail();
    $expectedRef = [
        'connection_id' => $connection->getKey(),
        'external_id' => $mapping->external_event_id,
        'etag' => $mapping->etag,
    ];

    // Cancellazione reale: l'observer (deleting) fotografa le mappature PRIMA del cascade
    // e accoda un OP_DELETE col payload dei riferimenti remoti.
    $appointment->delete();

    Bus::assertDispatched(
        SyncAppointmentToCalendarsJob::class,
        fn (SyncAppointmentToCalendarsJob $job): bool => $job->operation === SyncAppointmentToCalendarsJob::OP_DELETE
            && $job->deletions === [$expectedRef],
    );
});

it('never pushes an appointment to another organization connection (tenant isolation)', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();

    // Connessione attiva SOLO su B; l'appuntamento è di A.
    $connectionB = CalendarConnection::factory()->log()->active()->forOrganization($orgB)->create();
    $appointmentA = jobAppointment($orgA);

    runSync($appointmentA, SyncAppointmentToCalendarsJob::OP_UPSERT);

    // Nessuna mappatura verso la connessione di B, che resta non sincronizzata.
    assertDatabaseMissing('appointment_external_events', [
        'calendar_connection_id' => $connectionB->getKey(),
    ]);
    assertDatabaseCount('appointment_external_events', 0);
    expect($connectionB->refresh()->last_synced_at)->toBeNull();
});

it('is a no-op when the organization has no active connections', function () {
    $org = Organization::factory()->create();
    // Connessione presente ma NON attiva: non deve partecipare al push.
    CalendarConnection::factory()->log()->disconnected()->forOrganization($org)->create();
    $appointment = jobAppointment($org);

    runSync($appointment, SyncAppointmentToCalendarsJob::OP_UPSERT);

    assertDatabaseCount('appointment_external_events', 0);
});

it('returns quietly when the appointment is gone', function () {
    $org = Organization::factory()->create();
    CalendarConnection::factory()->log()->active()->forOrganization($org)->create();

    // Id inesistente: nessuna eccezione, nessuna scrittura.
    (new SyncAppointmentToCalendarsJob(999999, SyncAppointmentToCalendarsJob::OP_UPSERT))->handle(
        app(CalendarProviderRegistry::class),
        app(AppointmentEventMapper::class),
    );

    assertDatabaseCount('appointment_external_events', 0);
});

it('marks a failing connection as Error and still pushes to the healthy one', function () {
    $org = Organization::factory()->create();
    $healthy = CalendarConnection::factory()->log()->active()->forOrganization($org)->create(['name' => 'healthy']);
    $failing = CalendarConnection::factory()->log()->active()->forOrganization($org)->create(['name' => 'failing']);
    $appointment = jobAppointment($org);

    // Provider fittizio che fallisce SOLO sulla connessione "failing" e riesce (in modo
    // deterministico) sulle altre. Dichiara l'enum Log così, registrato nel registry,
    // sostituisce il binding del LogCalendarProvider reale per l'intera pipeline.
    $spy = new class implements ExternalCalendarProvider
    {
        public function provider(): CalendarProvider
        {
            return CalendarProvider::Log;
        }

        public function pushEvent(
            CalendarConnection $connection,
            CalendarEventData $event,
            ?string $existingExternalId,
            ?string $existingEtag,
        ): PushedEvent {
            if ($connection->name === 'failing') {
                throw new RuntimeException('boom');
            }

            return new PushedEvent('spy-'.$event->appointmentId, 'spy-etag');
        }

        public function deleteEvent(CalendarConnection $connection, string $externalId, ?string $etag): void {}

        public function pullChanges(CalendarConnection $connection): PulledChanges
        {
            return PulledChanges::empty();
        }
    };

    $registry = new CalendarProviderRegistry;
    $registry->register($spy);
    app()->instance(CalendarProviderRegistry::class, $registry);

    runSync($appointment, SyncAppointmentToCalendarsJob::OP_UPSERT);

    // La connessione sana ha la mappatura ed è rimasta Active.
    assertDatabaseHas('appointment_external_events', [
        'appointment_id' => $appointment->getKey(),
        'calendar_connection_id' => $healthy->getKey(),
    ]);
    expect($healthy->refresh()->status)->toBe(CalendarConnectionStatus::Active);

    // La connessione rotta è passata a Error con last_error, e non ha mappatura.
    $failing->refresh();
    expect($failing->status)->toBe(CalendarConnectionStatus::Error)
        ->and($failing->last_error)->toBe('boom');
    assertDatabaseMissing('appointment_external_events', [
        'appointment_id' => $appointment->getKey(),
        'calendar_connection_id' => $failing->getKey(),
    ]);
});

it('confirms the log provider pull returns an empty change set', function () {
    $org = Organization::factory()->create();
    $connection = CalendarConnection::factory()->log()->active()->forOrganization($org)->create();

    $changes = app(LogCalendarProvider::class)->pullChanges($connection);

    expect($changes)->toBeInstanceOf(PulledChanges::class)
        ->and($changes->changed)->toBe([])
        ->and($changes->deletedExternalIds)->toBe([])
        ->and($changes->newSyncToken)->toBeNull();
});

it('is a proper ExternalCalendarProvider implementation', function () {
    expect(app(LogCalendarProvider::class))->toBeInstanceOf(ExternalCalendarProvider::class);
});
