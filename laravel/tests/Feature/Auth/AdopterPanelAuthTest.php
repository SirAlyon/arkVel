<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

/*
 | Il pannello adottante /account senza pagine di autenticazione proprie (UI-5): ospiti →
 | /accedi, utenti da verificare → /verifica-email, "Esci" → logout pubblico (home).
 | /manage e /admin tengono il login di Filament.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('no longer registers sign-in, sign-up, password or verification pages on /account', function (): void {
    foreach (['login', 'register', 'password-reset.request', 'password-reset.reset', 'email-verification.prompt', 'email-verification.verify'] as $page) {
        expect(Route::has("filament.adopter.auth.{$page}"))->toBeFalse("filament.adopter.auth.{$page} still exists");
    }

    $panel = Filament::getPanel('adopter');
    expect($panel->hasLogin())->toBeFalse()
        ->and($panel->hasRegistration())->toBeFalse()
        ->and($panel->hasPasswordReset())->toBeFalse()
        ->and($panel->hasEmailVerification())->toBeFalse()
        ->and($panel->hasProfile())->toBeTrue();
});

it('sends a guest from /account to the public sign-in page', function (): void {
    $this->get('/account')->assertRedirect(route('login'));
});

it('remembers the adopter page a guest asked for and returns there after signing in', function (): void {
    $page = route('filament.adopter.pages.favorites');

    $this->get($page)->assertRedirect(route('login'));
    expect(session('url.intended'))->toBe($page);
});

it('sends an unverified user from /account to the public verification notice', function (): void {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('filament.adopter.pages.manage-adopter-profile'))
        ->assertRedirect(route('verification.notice'));

    $this->actingAs($user)
        ->get('/account')
        ->assertRedirect(route('verification.notice'));
});

it('still gives the Adopter role to an unverified user entering /account', function (): void {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get('/account');

    expect(DB::table('model_has_roles')
        ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
        ->where('model_has_roles.model_id', $user->getKey())
        ->where('roles.name', RoleName::Adopter->value)
        ->exists())->toBeTrue();
});

it('lets a verified user into /account', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('filament.adopter.pages.manage-adopter-profile'))
        ->assertOk();
});

it('points the "Sign out" item of the adopter user menu to the public logout', function (): void {
    $this->actingAs(User::factory()->create());
    Filament::setCurrentPanel(Filament::getPanel('adopter'));

    $logout = Filament::getPanel('adopter')->getUserMenuItems()['logout'];

    expect($logout->getUrl())->toBe(route('logout'))
        ->and($logout->shouldPostToUrl())->toBeTrue();

    $this->post(route('logout'))->assertRedirect(route('home'));
    $this->assertGuest();
});

it('renders the adopter panel with the public logout form in the user menu', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('filament.adopter.pages.manage-adopter-profile'))
        ->assertOk()
        ->assertSee('action="'.route('logout').'"', false);
});

it('keeps the Filament sign-in on /manage and /admin', function (string $panel, string $path): void {
    expect(Route::has("filament.{$panel}.auth.login"))->toBeTrue();

    $this->get($path)->assertRedirect(route("filament.{$panel}.auth.login"));
    $this->get(route("filament.{$panel}.auth.login"))->assertOk();
})->with([
    'shelter' => ['shelter', '/manage'],
    'admin' => ['admin', '/admin'],
]);

it('keeps the Filament registration, password reset and verification on /manage', function (): void {
    foreach (['register', 'password-reset.request', 'email-verification.prompt'] as $page) {
        expect(Route::has("filament.shelter.auth.{$page}"))->toBeTrue();
    }
});
