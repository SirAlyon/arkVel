<?php

declare(strict_types=1);

use App\Enums\CalendarConnectionStatus;
use App\Enums\CalendarProvider;
use App\Enums\CalendarSyncDirection;
use App\Enums\MembershipRole;
use App\Filament\Shelter\Pages\CalendarConnections;
use App\Models\CalendarConnection;
use App\Models\Organization;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

// Riusa member()/actAsInTenant() da tests/Pest.php.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Http::preventStrayRequests();
});

afterEach(function () {
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

/**
 * Il PROPFIND di verifica riesce (207): un server CalDAV valido e raggiungibile.
 */
function fakeCalDavReachable(): void
{
    Http::fake([
        '*' => Http::response('<d:multistatus xmlns:d="DAV:"/>', 207),
    ]);
}

it('creates an encrypted CalDAV connection for the tenant when verify succeeds', function () {
    fakeCalDavReachable();

    $org = Organization::factory()->verified()->create();
    $staff = member($org, MembershipRole::Staff);
    actAsInTenant($staff, $org);

    $collectionUrl = 'https://caldav.fastmail.com/dav/calendars/user/x@y.test/work/';

    Livewire::test(CalendarConnections::class)
        ->callAction('connectCalDav', data: [
            'url' => $collectionUrl,
            'username' => 'x@y.test',
            'app_password' => 'super-secret-app-pass',
        ]);

    assertDatabaseHas('calendar_connections', [
        'organization_id' => $org->getKey(),
        'provider' => CalendarProvider::CalDav->value,
        'external_calendar_id' => $collectionUrl,
    ]);

    $connection = CalendarConnection::query()
        ->where('organization_id', $org->getKey())
        ->where('provider', CalendarProvider::CalDav)
        ->firstOrFail();

    expect($connection->status)->toBe(CalendarConnectionStatus::Active)
        ->and($connection->direction)->toBe(CalendarSyncDirection::Both)
        ->and($connection->credentials['url'])->toBe($collectionUrl)
        ->and($connection->credentials['username'])->toBe('x@y.test')
        ->and($connection->credentials['app_password'])->toBe('super-secret-app-pass');

    // Le credenziali sono cifrate at-rest: la colonna grezza NON contiene l'app-password.
    $raw = DB::table('calendar_connections')->where('id', $connection->getKey())->value('credentials');
    expect($raw)->not->toContain('super-secret-app-pass')
        ->and($raw)->not->toContain('x@y.test');

    // La verifica è passata da un PROPFIND con Basic auth sull'URL fornito.
    Http::assertSent(fn ($request): bool => $request->method() === 'PROPFIND'
        && $request->url() === $collectionUrl
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('x@y.test:super-secret-app-pass')));
});

it('does NOT create a connection and shows an error when verify fails', function () {
    // Il server rifiuta le credenziali / non è un calendario: PROPFIND 401.
    Http::fake(['*' => Http::response('', 401)]);

    $org = Organization::factory()->verified()->create();
    $staff = member($org, MembershipRole::Staff);
    actAsInTenant($staff, $org);

    Livewire::test(CalendarConnections::class)
        ->callAction('connectCalDav', data: [
            'url' => 'https://caldav.example.test/dav/wrong/',
            'username' => 'bad@user.test',
            'app_password' => 'nope',
        ])
        ->assertNotified();

    assertDatabaseCount('calendar_connections', 0);
});

it('reconfigures the single CalDAV connection instead of duplicating it (updateOrCreate)', function () {
    fakeCalDavReachable();

    $org = Organization::factory()->verified()->create();
    $existing = CalendarConnection::factory()->calDav()->forOrganization($org)->create();

    $staff = member($org, MembershipRole::Staff);
    actAsInTenant($staff, $org);

    Livewire::test(CalendarConnections::class)
        ->callAction('connectCalDav', data: [
            'url' => 'https://caldav.example.test/dav/calendars/user/new@user.test/cal/',
            'username' => 'new@user.test',
            'app_password' => 'new-pass',
        ]);

    // Una sola connessione CalDAV per ente: quella esistente è stata riconfigurata.
    assertDatabaseCount('calendar_connections', 1);

    $existing->refresh();
    expect($existing->external_calendar_id)->toBe('https://caldav.example.test/dav/calendars/user/new@user.test/cal/')
        ->and($existing->credentials['username'])->toBe('new@user.test');
});

it('denies the CalDAV connect action to a volunteer (no calendar.manage)', function () {
    $org = Organization::factory()->verified()->create();
    $volunteer = member($org, MembershipRole::Volunteer);
    actAsInTenant($volunteer, $org);

    // Il volontario non può nemmeno vedere la pagina (canAccess = false): niente azione.
    expect(CalendarConnections::canAccess())->toBeFalse()
        ->and($volunteer->can('create', CalendarConnection::class))->toBeFalse();
});

it('only creates the connection for the current tenant (cross-tenant isolation)', function () {
    fakeCalDavReachable();

    $orgA = Organization::factory()->verified()->create();
    $orgB = Organization::factory()->verified()->create();

    // Staff di A agisce nel tenant A: la connessione nasce SOLO per A, mai per B.
    $staffA = member($orgA, MembershipRole::Staff);
    actAsInTenant($staffA, $orgA);

    Livewire::test(CalendarConnections::class)
        ->callAction('connectCalDav', data: [
            'url' => 'https://caldav.example.test/dav/calendars/user/a@user.test/cal/',
            'username' => 'a@user.test',
            'app_password' => 'a-pass',
        ]);

    assertDatabaseHas('calendar_connections', [
        'organization_id' => $orgA->getKey(),
        'provider' => CalendarProvider::CalDav->value,
    ]);

    expect(CalendarConnection::query()->where('organization_id', $orgB->getKey())->count())->toBe(0);
});

it('enables the CalDAV connect action (no longer a placeholder)', function () {
    $org = Organization::factory()->verified()->create();
    $staff = member($org, MembershipRole::Staff);
    actAsInTenant($staff, $org);

    Livewire::test(CalendarConnections::class)
        ->assertActionEnabled('connectCalDav');
});
