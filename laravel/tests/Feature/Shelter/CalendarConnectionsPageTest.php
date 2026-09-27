<?php

declare(strict_types=1);

use App\Enums\CalendarProvider;
use App\Enums\MembershipRole;
use App\Filament\Shelter\Pages\CalendarConnections;
use App\Models\AppointmentExternalEvent;
use App\Models\CalendarConnection;
use App\Models\Organization;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\assertDatabaseMissing;

// Riusa gli helper condivisi member()/actAsInTenant() da tests/Pest.php.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

afterEach(function () {
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

it('lists only the current tenant connections (cross-tenant isolation)', function () {
    $orgA = Organization::factory()->verified()->create();
    $orgB = Organization::factory()->verified()->create();

    $connA = CalendarConnection::factory()->log()->forOrganization($orgA)->create(['name' => 'Alpha calendar']);
    $connB = CalendarConnection::factory()->log()->forOrganization($orgB)->create(['name' => 'Beta calendar']);

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(CalendarConnections::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$connA])
        ->assertCanNotSeeTableRecords([$connB]);
});

it('lets staff create a demo (log) connection for the tenant', function () {
    $org = Organization::factory()->verified()->create();
    $staff = member($org, MembershipRole::Staff);
    actAsInTenant($staff, $org);

    Livewire::test(CalendarConnections::class)
        ->callAction('connectDemo');

    assertDatabaseHas('calendar_connections', [
        'organization_id' => $org->getKey(),
        'provider' => CalendarProvider::Log->value,
    ]);
});

it('lets staff disconnect (delete) a connection of the tenant', function () {
    $org = Organization::factory()->verified()->create();
    $connection = CalendarConnection::factory()->log()->forOrganization($org)->create();

    $staff = member($org, MembershipRole::Staff);
    actAsInTenant($staff, $org);

    Livewire::test(CalendarConnections::class)
        ->callAction(TestAction::make('disconnect')->table($connection));

    assertDatabaseMissing('calendar_connections', [
        'id' => $connection->getKey(),
    ]);
});

it('cascades the external-event mappings when a connection is disconnected', function () {
    $org = Organization::factory()->verified()->create();
    $connection = CalendarConnection::factory()->log()->forOrganization($org)->create();
    $mapping = AppointmentExternalEvent::factory()->forConnection($connection)->create();

    $staff = member($org, MembershipRole::Staff);
    actAsInTenant($staff, $org);

    Livewire::test(CalendarConnections::class)
        ->callAction(TestAction::make('disconnect')->table($connection));

    assertDatabaseMissing('appointment_external_events', [
        'id' => $mapping->getKey(),
    ]);
});

it('denies the page to a volunteer (no calendar.manage permission)', function () {
    $org = Organization::factory()->verified()->create();
    $volunteer = member($org, MembershipRole::Volunteer);
    actAsInTenant($volunteer, $org);

    expect(CalendarConnections::canAccess())->toBeFalse();
});

it('denies a volunteer authorization to manage a connection (policy)', function () {
    $org = Organization::factory()->verified()->create();
    $connection = CalendarConnection::factory()->log()->forOrganization($org)->create();

    $volunteer = member($org, MembershipRole::Volunteer);
    actAsInTenant($volunteer, $org);

    // Il volontario non ha CalendarManage: la policy nega vista/creazione/gestione,
    // quindi né la pagina né le azioni gli sono accessibili (difesa oltre alla UI).
    expect($volunteer->can('viewAny', CalendarConnection::class))->toBeFalse()
        ->and($volunteer->can('create', CalendarConnection::class))->toBeFalse()
        ->and($volunteer->can('delete', $connection))->toBeFalse();
});

it('disables the Google connect action when unconfigured but enables CalDAV', function () {
    // In test GOOGLE_CALENDAR_* è assente: l'azione Google è disabilitata perché non
    // configurata (config-gating). CalDAV (Cal-6) è per-connessione, senza config-gating:
    // la sua azione di connessione è SEMPRE abilitata.
    $org = Organization::factory()->verified()->create();
    $staff = member($org, MembershipRole::Staff);
    actAsInTenant($staff, $org);

    Livewire::test(CalendarConnections::class)
        ->assertActionDisabled('connectGoogle')
        ->assertActionEnabled('connectCalDav');
});
