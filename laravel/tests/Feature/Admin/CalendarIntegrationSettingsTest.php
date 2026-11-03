<?php

declare(strict_types=1);

use App\Enums\MembershipRole;
use App\Enums\RoleName;
use App\Filament\Pages\CalendarIntegrationSettings;
use App\Models\Organization;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Support\Calendar\Google\GoogleCalendarConfig;
use App\Support\Calendar\Google\GoogleOAuth;
use App\Support\Settings\PlatformSettings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    // Pannello Admin (dove la pagina è scoperta) e .env vuoto (come in CI).
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    config()->set('services.google_calendar.client_id', null);
    config()->set('services.google_calendar.client_secret', null);
    config()->set('services.google_calendar.redirect', null);
});

afterEach(function () {
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

/**
 * Crea un Super Admin di piattaforma (ruolo team-independent).
 */
function superAdminUser(): User
{
    $user = User::factory()->create();

    // In modalità teams l'assegnazione richiede un team id, ma è irrilevante per il
    // Super Admin (isSuperAdmin() rileva il ruolo TEAM-INDEPENDENT).
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId(0);
    $user->assignRole(RoleName::SuperAdmin->value);
    $registrar->forgetCachedPermissions();

    return $user;
}

it('lets a super-admin open the page', function () {
    Livewire::actingAs(superAdminUser())
        ->test(CalendarIntegrationSettings::class)
        ->assertOk();
});

it('denies access to a plain user', function () {
    $user = User::factory()->create();

    expect(CalendarIntegrationSettings::canAccess())->toBeFalse();

    Livewire::actingAs($user)
        ->test(CalendarIntegrationSettings::class)
        ->assertForbidden();
});

it('denies access to org staff who is not a platform super-admin', function () {
    $org = Organization::factory()->verified()->create();
    $staff = member($org, MembershipRole::Staff);

    Livewire::actingAs($staff)
        ->test(CalendarIntegrationSettings::class)
        ->assertForbidden();
});

it('persists client_id, client_secret and redirect_uri', function () {
    Livewire::actingAs(superAdminUser())
        ->test(CalendarIntegrationSettings::class)
        ->fillForm([
            'client_id' => 'my-client-id',
            'client_secret' => 'super-secret-value',
            'redirect_uri' => 'https://arkvel.test/calendar/google/callback',
        ])
        ->call('save')
        ->assertHasNoErrors();

    $settings = app(PlatformSettings::class);

    expect($settings->get(GoogleCalendarConfig::KEY_CLIENT_ID))->toBe('my-client-id')
        ->and($settings->get(GoogleCalendarConfig::KEY_CLIENT_SECRET))->toBe('super-secret-value')
        ->and($settings->get(GoogleCalendarConfig::KEY_REDIRECT_URI))->toBe('https://arkvel.test/calendar/google/callback');
});

it('stores the value ENCRYPTED at rest', function () {
    app(PlatformSettings::class)->set(GoogleCalendarConfig::KEY_CLIENT_SECRET, 'plaintext-secret');

    // Il valore grezzo nel DB non deve essere il testo in chiaro (è cifrato)...
    $raw = DB::table('platform_settings')
        ->where('key', GoogleCalendarConfig::KEY_CLIENT_SECRET)
        ->value('value');

    expect($raw)->not->toBeNull()
        ->and($raw)->not->toBe('plaintext-secret')
        ->and($raw)->not->toContain('plaintext-secret');

    // ...ma il valore decifrato dal model corrisponde al testo in chiaro.
    $model = PlatformSetting::query()->where('key', GoogleCalendarConfig::KEY_CLIENT_SECRET)->firstOrFail();

    expect($model->value)->toBe('plaintext-secret');
});

it('does NOT wipe a previously stored secret when submitting an empty client_secret', function () {
    app(PlatformSettings::class)->set(GoogleCalendarConfig::KEY_CLIENT_SECRET, 'existing-secret');

    Livewire::actingAs(superAdminUser())
        ->test(CalendarIntegrationSettings::class)
        ->fillForm([
            'client_id' => 'new-client-id',
            'client_secret' => '', // vuoto: NON deve toccare il segreto esistente
            'redirect_uri' => '',
        ])
        ->call('save')
        ->assertHasNoErrors();

    // Fresh store (memo invalidato tra request logiche): il segreto è intatto.
    expect(app(PlatformSettings::class)->get(GoogleCalendarConfig::KEY_CLIENT_SECRET))
        ->toBe('existing-secret');

    expect(app(PlatformSettings::class)->get(GoogleCalendarConfig::KEY_CLIENT_ID))
        ->toBe('new-client-id');
});

it('never renders the stored secret back into the form', function () {
    app(PlatformSettings::class)->set(GoogleCalendarConfig::KEY_CLIENT_SECRET, 'top-secret');
    app(PlatformSettings::class)->set(GoogleCalendarConfig::KEY_CLIENT_ID, 'the-client');

    $component = Livewire::actingAs(superAdminUser())
        ->test(CalendarIntegrationSettings::class)
        ->assertOk();

    // Il client_id (non sensibile) è precaricato, il segreto NO.
    $component->assertSet('data.client_id', 'the-client');
    $component->assertSet('data.client_secret', null);
    $component->assertDontSee('top-secret');
});

it('reflects DB-provided credentials in GoogleOAuth::configured() with an empty .env', function () {
    // .env vuoto (beforeEach) → non configurato.
    expect(GoogleOAuth::configured())->toBeFalse();

    app(PlatformSettings::class)->set(GoogleCalendarConfig::KEY_CLIENT_ID, 'db-client');
    app(PlatformSettings::class)->set(GoogleCalendarConfig::KEY_CLIENT_SECRET, 'db-secret');

    // Nuova risoluzione: il resolver (via container) legge il DB fresco.
    app()->forgetInstance(PlatformSettings::class);

    expect(GoogleOAuth::configured())->toBeTrue();
});
