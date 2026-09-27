<?php

declare(strict_types=1);

use App\Enums\CalendarProvider;
use App\Enums\CalendarSyncDirection;
use App\Enums\MembershipRole;
use App\Models\Animal;
use App\Models\Appointment;
use App\Models\AppointmentExternalEvent;
use App\Models\CalendarConnection;
use App\Models\Organization;
use App\Support\Calendar\CalendarEventData;
use App\Support\Calendar\CalendarProviderRegistry;
use App\Support\Calendar\Providers\ExternalCalendarProvider;
use App\Support\Calendar\Providers\PulledChanges;
use App\Support\Calendar\Providers\PulledEvent;
use App\Support\Calendar\Providers\PushedEvent;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Bus::fake();
    Notification::fake();
});

afterEach(function () {
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

/**
 * Provider finto CONNECTION-AWARE: restituisce le modifiche pull mappate per id di
 * connessione. Così, se il comando pullasse una connessione che NON deve toccare, lo
 * scopriremmo (le sue modifiche verrebbero applicate).
 *
 * @param  array<int, PulledChanges>  $changesByConnectionId
 */
function commandFakeProvider(array $changesByConnectionId): void
{
    $provider = new class($changesByConnectionId) implements ExternalCalendarProvider
    {
        /** @param array<int, PulledChanges> $map */
        public function __construct(public array $map) {}

        public function provider(): CalendarProvider
        {
            return CalendarProvider::Log;
        }

        public function pushEvent(CalendarConnection $connection, CalendarEventData $event, ?string $existingExternalId, ?string $existingEtag): PushedEvent
        {
            return new PushedEvent('log-'.$event->appointmentId, 'e');
        }

        public function deleteEvent(CalendarConnection $connection, string $externalId, ?string $etag): void {}

        public function pullChanges(CalendarConnection $connection): PulledChanges
        {
            return $this->map[$connection->getKey()] ?? PulledChanges::empty();
        }
    };

    $registry = new CalendarProviderRegistry;
    $registry->register($provider);
    app()->instance(CalendarProviderRegistry::class, $registry);
}

function commandAppointmentWithMapping(Organization $org, CalendarConnection $connection, string $externalId): Appointment
{
    $animal = Animal::factory()->forOrganization($org)->create();
    $appointment = Appointment::factory()->confirmed()->forAnimal($animal)->create([
        'scheduled_at' => now()->addDays(3)->startOfHour(),
    ]);
    AppointmentExternalEvent::factory()->create([
        'appointment_id' => $appointment->getKey(),
        'calendar_connection_id' => $connection->getKey(),
        'external_event_id' => $externalId,
        'etag' => 'old',
    ]);

    return $appointment;
}

it('reconciles active two-way connections and reports a summary', function () {
    $org = Organization::factory()->verified()->create();
    member($org, MembershipRole::Staff);
    $connection = CalendarConnection::factory()->log()->active()->forOrganization($org)
        ->create(['direction' => CalendarSyncDirection::Both]);
    $appointment = commandAppointmentWithMapping($org, $connection, 'ext-1');

    $newStart = now()->addDays(8)->startOfHour();
    commandFakeProvider([
        $connection->getKey() => new PulledChanges(
            changed: [new PulledEvent('ext-1', $appointment->getKey(), $newStart, 'confirmed', 'e')],
        ),
    ]);

    $this->artisan('arkvel:sync-calendars')->assertSuccessful();

    expect($appointment->fresh()->scheduled_at->equalTo($newStart))->toBeTrue();
});

it('does not process push-only connections', function () {
    $org = Organization::factory()->verified()->create();
    member($org, MembershipRole::Staff);
    $connection = CalendarConnection::factory()->log()->active()->forOrganization($org)
        ->create(['direction' => CalendarSyncDirection::Push]);
    $appointment = commandAppointmentWithMapping($org, $connection, 'ext-1');

    commandFakeProvider([
        $connection->getKey() => new PulledChanges(
            changed: [new PulledEvent('ext-1', $appointment->getKey(), now()->addDays(8)->startOfHour(), 'confirmed', 'e')],
        ),
    ]);

    $this->artisan('arkvel:sync-calendars')->assertSuccessful();

    // Connessione push-only: il pull non gira, l'appuntamento non è riprogrammato.
    expect($appointment->fresh()->scheduled_at->equalTo(now()->addDays(3)->startOfHour()))->toBeTrue();
});

it('targets a single connection with the --connection option', function () {
    $org = Organization::factory()->verified()->create();
    member($org, MembershipRole::Staff);

    $target = CalendarConnection::factory()->log()->active()->forOrganization($org)
        ->create(['direction' => CalendarSyncDirection::Both]);
    $other = CalendarConnection::factory()->log()->active()->forOrganization($org)
        ->create(['direction' => CalendarSyncDirection::Both]);

    $onTarget = commandAppointmentWithMapping($org, $target, 'ext-target');
    $onOther = commandAppointmentWithMapping($org, $other, 'ext-other');

    // Ogni connessione, SE pullata, sposta il proprio appuntamento. Con --connection=target
    // solo la connessione target viene pullata → solo il suo appuntamento si muove.
    $start = now()->addDays(8)->startOfHour();
    commandFakeProvider([
        $target->getKey() => new PulledChanges(changed: [new PulledEvent('ext-target', $onTarget->getKey(), $start, 'confirmed', 'e')]),
        $other->getKey() => new PulledChanges(changed: [new PulledEvent('ext-other', $onOther->getKey(), $start, 'confirmed', 'e')]),
    ]);

    $this->artisan('arkvel:sync-calendars', ['--connection' => $target->getKey()])->assertSuccessful();

    // Solo la connessione target è stata processata → solo il suo appuntamento è spostato.
    expect($onTarget->fresh()->scheduled_at->equalTo($start))->toBeTrue()
        ->and($onOther->fresh()->scheduled_at->equalTo(now()->addDays(3)->startOfHour()))->toBeTrue();
});
