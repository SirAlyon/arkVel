<?php

declare(strict_types=1);

use App\Actions\Auth\AuthenticateUser;
use App\Enums\MembershipRole;
use App\Enums\RoleName;
use App\Livewire\Auth\LoginPage;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

/*
 | /accedi (route `login`, UI-5): pagina Livewire pubblica che sostituisce la login del
 | pannello Filament adottante. Logica in App\Actions\Auth\AuthenticateUser.
 */

it('renders the public sign-in page with the themeable layout and noindex', function (): void {
    $this->get(route('login'))
        ->assertOk()
        ->assertSeeLivewire(LoginPage::class)
        ->assertSee('<title>Accedi — ArkVel</title>', false)
        ->assertSee('<meta name="robots" content="noindex, follow">', false)
        ->assertSee('<link rel="canonical" href="'.route('login').'">', false)
        ->assertDontSee('data-theme-forced', false)
        ->assertSee('autocomplete="current-password"', false)
        ->assertSee('autocomplete="email"', false)
        ->assertSee('href="'.route('password.request').'"', false)
        ->assertSee('href="'.route('register').'"', false);
});

it('is in English when the visitor chose English', function (): void {
    $this->withSession(['locale' => 'en'])
        ->get(route('login'))
        ->assertSee('<title>Sign in — ArkVel</title>', false)
        ->assertSee('Remember me on this device');
});

it('signs the user in, regenerates the session and goes to the adopter area', function (): void {
    $user = User::factory()->create(['password' => 'secret-password']);
    $sessionId = session()->getId();

    Livewire::test(LoginPage::class)
        ->set('email', $user->email)
        ->set('password', 'secret-password')
        ->call('authenticate')
        ->assertHasNoErrors()
        ->assertRedirect(route('filament.adopter.home'));

    $this->assertAuthenticatedAs($user);
    expect(session()->getId())->not->toBe($sessionId);
});

it('goes back to the intended internal page after signing in', function (): void {
    $user = User::factory()->create(['password' => 'secret-password']);
    session()->put('url.intended', url('/animali/7'));

    Livewire::test(LoginPage::class)
        ->set('email', $user->email)
        ->set('password', 'secret-password')
        ->call('authenticate')
        ->assertRedirect(url('/animali/7'));
});

it('ignores an external intended URL', function (): void {
    $user = User::factory()->create(['password' => 'secret-password']);
    session()->put('url.intended', 'https://evil.example/phish');

    Livewire::test(LoginPage::class)
        ->set('email', $user->email)
        ->set('password', 'secret-password')
        ->call('authenticate')
        ->assertRedirect(route('filament.adopter.home'));
});

it('remembers the user when asked', function (): void {
    $user = User::factory()->create(['password' => 'secret-password', 'remember_token' => null]);

    Livewire::test(LoginPage::class)
        ->set('email', $user->email)
        ->set('password', 'secret-password')
        ->set('remember', true)
        ->call('authenticate');

    expect($user->fresh()->remember_token)->not->toBeNull();
});

/** Ha il ruolo Adopter (rilevato TEAM-INDEPENDENT, come EnsureAdopterRole). */
function hasAdopterRole(User $user): bool
{
    return DB::table('model_has_roles')
        ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
        ->where('model_has_roles.model_id', $user->getKey())
        ->where('roles.name', RoleName::Adopter->value)
        ->exists();
}

it('sends a member of an organization to /manage, without the Adopter role', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $member = member(Organization::factory()->verified()->create(), MembershipRole::Volunteer);
    $member->forceFill(['password' => 'secret-password'])->save();

    Livewire::test(LoginPage::class)
        ->set('email', $member->email)
        ->set('password', 'secret-password')
        ->call('authenticate')
        ->assertRedirect(route('filament.shelter.tenant'));

    $this->get(route('filament.shelter.tenant'));

    $this->assertAuthenticatedAs($member);
    expect(hasAdopterRole($member))->toBeFalse();
});

it('sends the Super Admin to /admin', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $admin = User::factory()->create(['password' => 'secret-password']);
    app(PermissionRegistrar::class)->setPermissionsTeamId(RoleName::PLATFORM_TEAM_ID);
    $admin->assignRole(RoleName::SuperAdmin->value);

    Livewire::test(LoginPage::class)
        ->set('email', $admin->email)
        ->set('password', 'secret-password')
        ->call('authenticate')
        ->assertRedirect(route('filament.admin.pages.dashboard'));
});

it('sends a signed-in member of an organization opening /accedi to /manage, without the Adopter role', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $member = member(Organization::factory()->verified()->create(), MembershipRole::Owner);

    $this->actingAs($member)
        ->get(route('login'))
        ->assertRedirect(route('filament.shelter.tenant'));

    expect(hasAdopterRole($member))->toBeFalse();
});

it('accepts the email with surrounding spaces and capital letters', function (): void {
    $user = User::factory()->create(['email' => 'mario@example.com', 'password' => 'secret-password']);

    Livewire::test(LoginPage::class)
        ->set('email', '  Mario@Example.COM ')
        ->set('password', 'secret-password')
        ->call('authenticate')
        ->assertHasNoErrors();

    $this->assertAuthenticatedAs($user);
});

it('clears the password after a failed attempt or a validation error', function (): void {
    User::factory()->create(['email' => 'mario@example.com', 'password' => 'secret-password']);

    Livewire::test(LoginPage::class)
        ->set('email', 'mario@example.com')
        ->set('password', 'wrong-password')
        ->call('authenticate')
        ->assertHasErrors(['email'])
        ->assertSet('password', '')
        ->assertSet('email', 'mario@example.com');

    Livewire::test(LoginPage::class)
        ->set('email', 'not-an-email')
        ->set('password', 'secret-password')
        ->call('authenticate')
        ->assertHasErrors(['email'])
        ->assertSet('password', '');
});

it('does not sign in again when the session signed in meanwhile', function (): void {
    $user = User::factory()->create(['password' => 'secret-password']);
    $other = User::factory()->create(['password' => 'secret-password']);
    $component = Livewire::test(LoginPage::class)
        ->set('email', $other->email)
        ->set('password', 'secret-password');

    // Accesso fatto in un'altra scheda con un altro account.
    $this->actingAs($user);

    $component->call('authenticate')->assertRedirect(route('filament.adopter.home'));

    $this->assertAuthenticatedAs($user);
});

it('rejects wrong credentials with one message that does not reveal the account', function (string $email): void {
    User::factory()->create(['email' => 'mario@example.com', 'password' => 'secret-password']);

    Livewire::test(LoginPage::class)
        ->set('email', $email)
        ->set('password', 'wrong-password')
        ->call('authenticate')
        ->assertHasErrors(['email'])
        ->assertSee('Email o password non corretti.')
        ->assertSee('data-ui-error-summary', false)
        ->assertNoRedirect();

    $this->assertGuest();
})->with(['existing account' => 'mario@example.com', 'unknown account' => 'nobody@example.com']);

it('validates the form with translated messages', function (): void {
    Livewire::test(LoginPage::class)
        ->set('email', 'not-an-email')
        ->call('authenticate')
        ->assertHasErrors(['email' => 'email', 'password' => 'required'])
        ->assertSee('Scrivi un indirizzo email valido')
        ->assertSee('Scrivi la password.');
});

it('locks out after too many failed attempts for the same email and IP', function (): void {
    $user = User::factory()->create(['password' => 'secret-password']);

    foreach (range(1, AuthenticateUser::MAX_ATTEMPTS) as $attempt) {
        Livewire::test(LoginPage::class)
            ->set('email', $user->email)
            ->set('password', 'wrong-password')
            ->call('authenticate')
            ->assertHasErrors(['email']);
    }

    // Anche la password giusta ora è respinta finché il limite non scade.
    Livewire::test(LoginPage::class)
        ->set('email', $user->email)
        ->set('password', 'secret-password')
        ->call('authenticate')
        ->assertHasErrors(['email'])
        ->assertSee('Troppi tentativi');

    $this->assertGuest();

    $this->travel(AuthenticateUser::DECAY_SECONDS + 1)->seconds();

    Livewire::test(LoginPage::class)
        ->set('email', $user->email)
        ->set('password', 'secret-password')
        ->call('authenticate')
        ->assertHasNoErrors();

    $this->assertAuthenticatedAs($user);
});

it('sends signed-in visitors to their area', function (): void {
    $this->actingAs(User::factory()->create())
        ->get(route('login'))
        ->assertRedirect(route('filament.adopter.home'));
});
