<?php

declare(strict_types=1);

use App\Enums\MembershipRole;
use App\Enums\RoleName;
use App\Filament\Pages\StripeIntegrationSettings;
use App\Models\Organization;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Support\Billing\StripeConfig;
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

    // Pannello Admin (dove la pagina è scoperta) e .env Stripe vuoto (come in CI).
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    config()->set('cashier.key', null);
    config()->set('cashier.secret', null);
    config()->set('cashier.webhook.secret', null);
});

afterEach(function () {
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

/**
 * Crea un Super Admin di piattaforma (ruolo team-independent).
 */
function stripeSuperAdminUser(): User
{
    $user = User::factory()->create();

    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId(0);
    $user->assignRole(RoleName::SuperAdmin->value);
    $registrar->forgetCachedPermissions();

    return $user;
}

it('lets a super-admin open the page', function () {
    Livewire::actingAs(stripeSuperAdminUser())
        ->test(StripeIntegrationSettings::class)
        ->assertOk();
});

it('denies access to a plain user', function () {
    $user = User::factory()->create();

    expect(StripeIntegrationSettings::canAccess())->toBeFalse();

    Livewire::actingAs($user)
        ->test(StripeIntegrationSettings::class)
        ->assertForbidden();
});

it('denies access to org staff who is not a platform super-admin', function () {
    $org = Organization::factory()->verified()->create();
    $staff = member($org, MembershipRole::Staff);

    Livewire::actingAs($staff)
        ->test(StripeIntegrationSettings::class)
        ->assertForbidden();
});

it('persists publishable, secret and webhook keys', function () {
    Livewire::actingAs(stripeSuperAdminUser())
        ->test(StripeIntegrationSettings::class)
        ->fillForm([
            'publishable_key' => 'pk_test_abc',
            'secret_key' => 'sk_test_def',
            'webhook_secret' => 'whsec_ghi',
        ])
        ->call('save')
        ->assertHasNoErrors();

    $settings = app(PlatformSettings::class);

    expect($settings->get(StripeConfig::KEY_PUBLISHABLE))->toBe('pk_test_abc')
        ->and($settings->get(StripeConfig::KEY_SECRET))->toBe('sk_test_def')
        ->and($settings->get(StripeConfig::KEY_WEBHOOK_SECRET))->toBe('whsec_ghi');
});

it('stores the secret ENCRYPTED at rest', function () {
    app(PlatformSettings::class)->set(StripeConfig::KEY_SECRET, 'sk_live_plaintext');

    // Il valore grezzo nel DB non deve essere il testo in chiaro (è cifrato)...
    $raw = DB::table('platform_settings')
        ->where('key', StripeConfig::KEY_SECRET)
        ->value('value');

    expect($raw)->not->toBeNull()
        ->and($raw)->not->toBe('sk_live_plaintext')
        ->and($raw)->not->toContain('sk_live_plaintext');

    // ...ma il valore decifrato dal model corrisponde al testo in chiaro.
    $model = PlatformSetting::query()->where('key', StripeConfig::KEY_SECRET)->firstOrFail();

    expect($model->value)->toBe('sk_live_plaintext');
});

it('does NOT wipe previously stored secrets when submitting them empty', function () {
    app(PlatformSettings::class)->set(StripeConfig::KEY_SECRET, 'sk_existing');
    app(PlatformSettings::class)->set(StripeConfig::KEY_WEBHOOK_SECRET, 'whsec_existing');

    Livewire::actingAs(stripeSuperAdminUser())
        ->test(StripeIntegrationSettings::class)
        ->fillForm([
            'publishable_key' => 'pk_new',
            'secret_key' => '',       // vuoto: NON deve toccare il segreto esistente
            'webhook_secret' => '',   // idem
        ])
        ->call('save')
        ->assertHasNoErrors();

    $fresh = app(PlatformSettings::class);

    expect($fresh->get(StripeConfig::KEY_SECRET))->toBe('sk_existing')
        ->and($fresh->get(StripeConfig::KEY_WEBHOOK_SECRET))->toBe('whsec_existing')
        ->and($fresh->get(StripeConfig::KEY_PUBLISHABLE))->toBe('pk_new');
});

it('never renders the stored secret back into the form', function () {
    app(PlatformSettings::class)->set(StripeConfig::KEY_SECRET, 'sk_top_secret');
    app(PlatformSettings::class)->set(StripeConfig::KEY_PUBLISHABLE, 'pk_the_key');

    $component = Livewire::actingAs(stripeSuperAdminUser())
        ->test(StripeIntegrationSettings::class)
        ->assertOk();

    // La publishable key (non sensibile) è precaricata, la secret NO.
    $component->assertSet('data.publishable_key', 'pk_the_key');
    $component->assertSet('data.secret_key', null);
    $component->assertSet('data.webhook_secret', null);
    $component->assertDontSee('sk_top_secret');
});

it('reflects DB-provided credentials in StripeConfig::configured() with an empty .env', function () {
    // .env vuoto (beforeEach) → non configurato.
    expect(app(StripeConfig::class)->configured())->toBeFalse();

    app(PlatformSettings::class)->set(StripeConfig::KEY_PUBLISHABLE, 'pk_db');
    app(PlatformSettings::class)->set(StripeConfig::KEY_SECRET, 'sk_db');

    // Nuova risoluzione: il resolver (via container) legge il DB fresco.
    app()->forgetInstance(PlatformSettings::class);

    expect(app(StripeConfig::class)->configured())->toBeTrue()
        ->and(app(StripeConfig::class)->source())->toBe('db');
});
