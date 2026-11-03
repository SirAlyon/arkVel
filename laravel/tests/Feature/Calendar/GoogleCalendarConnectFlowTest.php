<?php

declare(strict_types=1);

use App\Enums\CalendarConnectionStatus;
use App\Enums\CalendarProvider;
use App\Enums\MembershipRole;
use App\Models\CalendarConnection;
use App\Models\Organization;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\get;

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
 * Configura le credenziali OAuth Google (altrimenti il flusso è inerte: 404).
 */
function configureGoogleCalendar(): void
{
    config([
        'services.google_calendar.client_id' => 'test-client-id',
        'services.google_calendar.client_secret' => 'test-client-secret',
        'services.google_calendar.redirect' => 'https://arkvel.test/calendar/google/callback',
    ]);
}

it('redirects a manager to Google consent and stores state + tenant in session', function () {
    configureGoogleCalendar();
    $org = Organization::factory()->verified()->create();
    $staff = member($org, MembershipRole::Staff);

    $response = actingAs($staff)->get(route('calendar.google.redirect', ['tenant' => $org->slug]));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('accounts.google.com/o/oauth2/v2/auth');
    $response->assertSessionHas('google_calendar.oauth_state');
    $response->assertSessionHas('google_calendar.tenant_id', $org->getKey());
});

it('returns 404 for the redirect when Google is not configured', function () {
    // Nessuna config: la funzione è inerte.
    $org = Organization::factory()->verified()->create();
    $staff = member($org, MembershipRole::Staff);

    actingAs($staff)
        ->get(route('calendar.google.redirect', ['tenant' => $org->slug]))
        ->assertNotFound();
});

it('denies the redirect to a volunteer (no calendar.manage)', function () {
    configureGoogleCalendar();
    $org = Organization::factory()->verified()->create();
    $volunteer = member($org, MembershipRole::Volunteer);

    actingAs($volunteer)
        ->get(route('calendar.google.redirect', ['tenant' => $org->slug]))
        ->assertForbidden();
});

it('denies connecting a calendar to an organization the user is not a member of', function () {
    configureGoogleCalendar();
    $orgA = Organization::factory()->verified()->create();
    $orgB = Organization::factory()->verified()->create();
    $staffA = member($orgA, MembershipRole::Staff);

    // Staff di A tenta di collegare il calendario di B → negato.
    actingAs($staffA)
        ->get(route('calendar.google.redirect', ['tenant' => $orgB->slug]))
        ->assertForbidden();
});

it('creates an encrypted Google connection for the tenant on a valid callback', function () {
    configureGoogleCalendar();
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'ya29.access',
            'refresh_token' => '1//refresh',
            'expires_in' => 3600,
            'scope' => 'https://www.googleapis.com/auth/calendar.events',
            'token_type' => 'Bearer',
        ]),
    ]);

    $org = Organization::factory()->verified()->create();
    $staff = member($org, MembershipRole::Staff);

    // Prepara la sessione come farebbe redirect().
    $response = actingAs($staff)
        ->withSession([
            'google_calendar.oauth_state' => 'the-state',
            'google_calendar.tenant_id' => $org->getKey(),
        ])
        ->get(route('calendar.google.callback', ['state' => 'the-state', 'code' => 'auth-code']));

    $response->assertRedirect();
    $response->assertSessionHas('calendar_success');

    $connection = CalendarConnection::query()
        ->where('organization_id', $org->getKey())
        ->where('provider', CalendarProvider::Google)
        ->firstOrFail();

    expect($connection->status)->toBe(CalendarConnectionStatus::Active)
        ->and($connection->external_calendar_id)->toBe('primary')
        ->and($connection->credentials['access_token'])->toBe('ya29.access')
        ->and($connection->credentials['refresh_token'])->toBe('1//refresh');

    // I token sono cifrati at-rest: la colonna grezza non contiene il token in chiaro.
    $raw = DB::table('calendar_connections')->where('id', $connection->getKey())->value('credentials');
    expect($raw)->not->toContain('ya29.access');
});

it('rejects a callback whose state does not match (CSRF) and creates no connection', function () {
    configureGoogleCalendar();

    $org = Organization::factory()->verified()->create();
    $staff = member($org, MembershipRole::Staff);

    actingAs($staff)
        ->withSession([
            'google_calendar.oauth_state' => 'expected-state',
            'google_calendar.tenant_id' => $org->getKey(),
        ])
        ->get(route('calendar.google.callback', ['state' => 'WRONG-state', 'code' => 'auth-code']))
        ->assertRedirect();

    assertDatabaseCount('calendar_connections', 0);
});

it('returns to connections with an error when the user denied consent (no code)', function () {
    configureGoogleCalendar();

    $org = Organization::factory()->verified()->create();
    $staff = member($org, MembershipRole::Staff);

    actingAs($staff)
        ->withSession([
            'google_calendar.oauth_state' => 'the-state',
            'google_calendar.tenant_id' => $org->getKey(),
        ])
        ->get(route('calendar.google.callback', ['state' => 'the-state', 'error' => 'access_denied']))
        ->assertRedirect()
        ->assertSessionHas('calendar_error');

    assertDatabaseCount('calendar_connections', 0);
});

it('requires authentication for the connect routes', function () {
    configureGoogleCalendar();
    $org = Organization::factory()->verified()->create();

    get(route('calendar.google.redirect', ['tenant' => $org->slug]))
        ->assertRedirect();
});
