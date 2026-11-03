<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Enums\CalendarProvider;
use App\Enums\MembershipRole;
use App\Models\Animal;
use App\Models\Appointment;
use App\Models\AppointmentExternalEvent;
use App\Models\CalendarConnection;
use App\Models\Organization;
use App\Notifications\AppointmentRescheduled;
use App\Support\Calendar\CalendarEventData;
use App\Support\Calendar\CalendarProviderRegistry;
use App\Support\Calendar\CalendarReconciler;
use App\Support\Calendar\Providers\ExternalCalendarProvider;
use App\Support\Calendar\Providers\PulledChanges;
use App\Support\Calendar\Providers\PulledEvent;
use App\Support\Calendar\Providers\PushedEvent;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;

// Riusa member() da tests/Pest.php.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    // Il push (observer→job) è irrilevante qui e clobbererebbe le mappature: lo isoliamo.
    Bus::fake();
    Notification::fake();
});

afterEach(function () {
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

/**
 * Registra un provider FINTO (enum Log) che restituisce le modifiche pull programmate,
 * così il reconciler gira contro dati controllati e deterministici.
 */
function fakePullProvider(PulledChanges $changes): void
{
    $provider = new class($changes) implements ExternalCalendarProvider
    {
        public function __construct(public PulledChanges $changes) {}

        public function provider(): CalendarProvider
        {
            return CalendarProvider::Log;
        }

        public function pushEvent(CalendarConnection $connection, CalendarEventData $event, ?string $existingExternalId, ?string $existingEtag): PushedEvent
        {
            return new PushedEvent('log-'.$event->appointmentId, 'log-etag');
        }

        public function deleteEvent(CalendarConnection $connection, string $externalId, ?string $etag): void {}

        public function pullChanges(CalendarConnection $connection): PulledChanges
        {
            return $this->changes;
        }
    };

    $registry = new CalendarProviderRegistry;
    $registry->register($provider);
    app()->instance(CalendarProviderRegistry::class, $registry);
}

/**
 * Ente con staff verificato (attore di sistema per la riconciliazione) + connessione attiva.
 *
 * @return array{org: Organization, connection: CalendarConnection}
 */
function reconcileOrg(): array
{
    $org = Organization::factory()->verified()->create();
    member($org, MembershipRole::Staff); // membro verificato → attore di sistema
    $connection = CalendarConnection::factory()->log()->active()->forOrganization($org)
        ->create(['sync_token' => 'token-before']);

    return ['org' => $org, 'connection' => $connection];
}

function reconcileAppointment(Organization $org, string $state = 'confirmed'): Appointment
{
    $animal = Animal::factory()->forOrganization($org)->create();

    return Appointment::factory()->{$state}()->forAnimal($animal)->create([
        'scheduled_at' => now()->addDays(3)->startOfHour(),
    ]);
}

function reconcileMapping(Appointment $appointment, CalendarConnection $connection, string $externalId, ?string $etag = 'old-etag'): AppointmentExternalEvent
{
    return AppointmentExternalEvent::factory()->create([
        'appointment_id' => $appointment->getKey(),
        'calendar_connection_id' => $connection->getKey(),
        'external_event_id' => $externalId,
        'etag' => $etag,
    ]);
}

it('reschedules an ArkVel appointment when the external start differs', function () {
    ['org' => $org, 'connection' => $connection] = reconcileOrg();
    $appointment = reconcileAppointment($org);
    reconcileMapping($appointment, $connection, 'ext-1');

    $newStart = now()->addDays(6)->startOfHour();
    fakePullProvider(new PulledChanges(
        changed: [new PulledEvent('ext-1', $appointment->getKey(), $newStart, 'confirmed', 'new-etag')],
        newSyncToken: 'token-after',
    ));

    $summary = app(CalendarReconciler::class)->reconcile($connection->fresh());

    expect($summary['rescheduled'])->toBe(1)
        ->and($appointment->fresh()->scheduled_at->equalTo($newStart))->toBeTrue()
        ->and(AppointmentExternalEvent::query()->firstOrFail()->etag)->toBe('new-etag')
        ->and($connection->fresh()->sync_token)->toBe('token-after');

    Notification::assertSentTo($appointment->user, AppointmentRescheduled::class);
});

it('is convergent: a second reconcile with the same start does nothing (no ping-pong)', function () {
    ['org' => $org, 'connection' => $connection] = reconcileOrg();
    $appointment = reconcileAppointment($org);
    reconcileMapping($appointment, $connection, 'ext-1');

    $start = now()->addDays(6)->startOfHour();
    fakePullProvider(new PulledChanges(
        changed: [new PulledEvent('ext-1', $appointment->getKey(), $start, 'confirmed', 'e')],
    ));

    app(CalendarReconciler::class)->reconcile($connection->fresh());
    // Secondo giro: lo start esterno combacia con lo scheduled_at → nessuna azione.
    $summary = app(CalendarReconciler::class)->reconcile($connection->fresh());

    expect($summary['rescheduled'])->toBe(0)
        ->and($summary['skipped'])->toBe(1);
});

it('cancels the appointment when the external event is cancelled', function () {
    ['org' => $org, 'connection' => $connection] = reconcileOrg();
    $appointment = reconcileAppointment($org);
    reconcileMapping($appointment, $connection, 'ext-1');

    fakePullProvider(new PulledChanges(
        changed: [new PulledEvent('ext-1', $appointment->getKey(), null, 'cancelled', 'e')],
    ));

    $summary = app(CalendarReconciler::class)->reconcile($connection->fresh());

    expect($summary['cancelled'])->toBe(1)
        ->and($appointment->fresh()->status)->toBe(AppointmentStatus::Cancelled);
});

it('cancels and drops the mapping when the external event is deleted', function () {
    ['org' => $org, 'connection' => $connection] = reconcileOrg();
    $appointment = reconcileAppointment($org);
    $mapping = reconcileMapping($appointment, $connection, 'ext-1');

    fakePullProvider(new PulledChanges(deletedExternalIds: ['ext-1']));

    $summary = app(CalendarReconciler::class)->reconcile($connection->fresh());

    expect($summary['cancelled'])->toBe(1)
        ->and($appointment->fresh()->status)->toBe(AppointmentStatus::Cancelled)
        ->and(AppointmentExternalEvent::query()->whereKey($mapping->getKey())->exists())->toBeFalse();
});

it('does nothing when the external start equals the ArkVel start', function () {
    ['org' => $org, 'connection' => $connection] = reconcileOrg();
    $appointment = reconcileAppointment($org);
    reconcileMapping($appointment, $connection, 'ext-1');

    fakePullProvider(new PulledChanges(
        changed: [new PulledEvent('ext-1', $appointment->getKey(), $appointment->scheduled_at->copy(), 'confirmed', 'e')],
    ));

    $summary = app(CalendarReconciler::class)->reconcile($connection->fresh());

    expect($summary['rescheduled'])->toBe(0)
        ->and($summary['cancelled'])->toBe(0);
    Notification::assertNothingSent();
});

it('skips a change referencing another organization appointment (tenant isolation)', function () {
    ['connection' => $connection] = reconcileOrg();
    $orgB = Organization::factory()->verified()->create();
    $appointmentB = reconcileAppointment($orgB);

    fakePullProvider(new PulledChanges(
        changed: [new PulledEvent('ext-foreign', $appointmentB->getKey(), now()->addDays(9)->startOfHour(), 'confirmed', 'e')],
    ));

    $summary = app(CalendarReconciler::class)->reconcile($connection->fresh());

    // L'appuntamento di B non è toccato dalla connessione di A.
    expect($summary['skipped'])->toBe(1)
        ->and($appointmentB->fresh()->scheduled_at->equalTo(now()->addDays(9)->startOfHour()))->toBeFalse();
});

it('skips an item whose reschedule would land in the past, without aborting the others', function () {
    ['org' => $org, 'connection' => $connection] = reconcileOrg();
    $pastAppt = reconcileAppointment($org);
    reconcileMapping($pastAppt, $connection, 'ext-past');
    $okAppt = reconcileAppointment($org);
    reconcileMapping($okAppt, $connection, 'ext-ok');

    $future = now()->addDays(7)->startOfHour();
    fakePullProvider(new PulledChanges(changed: [
        // Slot nel passato: l'Action lancia InvalidAppointmentSlot → voce saltata.
        new PulledEvent('ext-past', $pastAppt->getKey(), now()->subDays(1)->startOfHour(), 'confirmed', 'e'),
        // Voce valida: deve comunque essere processata.
        new PulledEvent('ext-ok', $okAppt->getKey(), $future, 'confirmed', 'e'),
    ]));

    $summary = app(CalendarReconciler::class)->reconcile($connection->fresh());

    expect($summary['rescheduled'])->toBe(1)
        ->and($okAppt->fresh()->scheduled_at->equalTo($future))->toBeTrue()
        // Il passato non ha spostato nulla e la connessione resta sana.
        ->and($connection->fresh()->status->value)->toBe('active');
});

it('skips the connection gracefully when the organization has no verified staff', function () {
    $org = Organization::factory()->verified()->create(); // nessun membro verificato
    $connection = CalendarConnection::factory()->log()->active()->forOrganization($org)
        ->create(['sync_token' => 'unchanged']);
    $appointment = reconcileAppointment($org);
    reconcileMapping($appointment, $connection, 'ext-1');

    fakePullProvider(new PulledChanges(
        changed: [new PulledEvent('ext-1', $appointment->getKey(), now()->addDays(9)->startOfHour(), 'confirmed', 'e')],
    ));

    $summary = app(CalendarReconciler::class)->reconcile($connection->fresh());

    // Nessun attore → nessuna riconciliazione, sync_token invariato.
    expect($summary)->toBe(['rescheduled' => 0, 'cancelled' => 0, 'skipped' => 0])
        ->and($connection->fresh()->sync_token)->toBe('unchanged')
        ->and($appointment->fresh()->scheduled_at->equalTo(now()->addDays(9)->startOfHour()))->toBeFalse();
});
