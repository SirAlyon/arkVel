<?php

declare(strict_types=1);

use App\Actions\Organizations\AddOrganizationMember;
use App\Enums\MembershipRole;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\Auth\ResetPasswordLink;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Auth\Notifications\ResetPassword;
use Filament\Auth\Notifications\VerifyEmail;
use Filament\Auth\Pages\Login;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Filament\Auth\Pages\Register;
use Filament\Facades\Filament;
use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    // Il pannello shelter deve essere quello corrente perché le pagine di auth
    // (e la risoluzione del broker/model utente) usino la sua configurazione.
    Filament::setCurrentPanel(Filament::getPanel('shelter'));
});

// Coerente con gli altri test del pannello: azzera lo stato in-memory del team
// id sul registrar spatie dopo ogni test, evitando falsi positivi da residui.
afterEach(function () {
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

/**
 * Crea un ente operabile con un membro verificato, restituendo entrambi.
 *
 * @return array{0: Organization, 1: User}
 */
function verifiedShelterMember(): array
{
    $organization = Organization::factory()->verified()->create();
    $user = User::factory()->create();

    app(AddOrganizationMember::class)->handle(
        organization: $organization,
        user: $user,
        role: MembershipRole::Owner,
        verified: true,
    );

    return [$organization, $user];
}

// --- guest -> login -> redirect (RICHIESTO dalla WBS) --------------------------

it('redirects a guest visiting the shelter panel root to the shelter login', function () {
    $this->get('/manage')
        ->assertRedirect(route('filament.shelter.auth.login'));
});

it('redirects a guest visiting a protected tenant dashboard to the shelter login', function () {
    [$organization] = verifiedShelterMember();

    // La dashboard di un tenant è protetta: un ospite deve finire alla login.
    $this->get("/manage/{$organization->slug}")
        ->assertRedirect(route('filament.shelter.auth.login'));
});

// --- login ---------------------------------------------------------------------

it('reaches the shelter login page as a guest', function () {
    $this->get(route('filament.shelter.auth.login'))
        ->assertOk();
});

it('lets a verified member with a verified email authenticate', function () {
    [, $user] = verifiedShelterMember();

    Livewire::test(Login::class)
        ->fillForm([
            'email' => $user->email,
            'password' => 'password',
        ])
        ->call('authenticate')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    expect(Auth::check())->toBeTrue()
        ->and(Auth::id())->toBe($user->getKey());
});

it('rejects login with wrong credentials and does not authenticate', function () {
    [, $user] = verifiedShelterMember();

    Livewire::test(Login::class)
        ->fillForm([
            'email' => $user->email,
            'password' => 'wrong-password',
        ])
        ->call('authenticate')
        ->assertHasFormErrors(['email']);

    expect(Auth::check())->toBeFalse();
});

// --- registration --------------------------------------------------------------

it('reaches the shelter account registration page as a guest', function () {
    $this->get(route('filament.shelter.auth.register'))
        ->assertOk();
});

it('registers a new account and sends the email verification notification', function () {
    Notification::fake();

    Livewire::test(Register::class)
        ->fillForm([
            'name' => 'Nuovo Operatore',
            'email' => 'operatore@example.test',
            'password' => 'password-123',
            'passwordConfirmation' => 'password-123',
        ])
        ->call('register')
        ->assertHasNoFormErrors();

    $user = User::query()->where('email', 'operatore@example.test')->sole();

    // Un utente appena registrato NON è verificato (email_verified_at null).
    expect($user->hasVerifiedEmail())->toBeFalse();

    // Filament invia la propria VerifyEmail, sottoclasse della notification
    // Laravel: asseriamo la classe realmente inviata e ne documentiamo la
    // parentela, così il test non produce falsi positivi.
    expect(is_subclass_of(VerifyEmail::class, BaseVerifyEmail::class))->toBeTrue();
    Notification::assertSentTo($user, VerifyEmail::class);
});

// --- password reset ------------------------------------------------------------

it('reaches the password reset request page as a guest', function () {
    $this->get(route('filament.shelter.auth.password-reset.request'))
        ->assertOk();
});

it('sends the reset-password notification when a reset is requested', function () {
    Notification::fake();

    [, $user] = verifiedShelterMember();

    Livewire::test(RequestPasswordReset::class)
        ->fillForm(['email' => $user->email])
        ->call('request')
        ->assertHasNoFormErrors();

    // Come per la verifica email, Filament invia la propria ResetPassword,
    // sottoclasse della notification Laravel.
    expect(is_subclass_of(ResetPassword::class, BaseResetPassword::class))->toBeTrue();
    Notification::assertSentTo($user, ResetPassword::class);
});

it('keeps the Filament reset mail and link on /manage despite the public reset mail of the User model', function () {
    Notification::fake();

    [, $user] = verifiedShelterMember();

    Livewire::test(RequestPasswordReset::class)
        ->fillForm(['email' => $user->email])
        ->call('request')
        ->assertHasNoFormErrors();

    // User::sendPasswordResetNotification() manda la mail pubblica (UI-5): il pannello
    // usa la callback propria del broker, quindi la notifica e il link restano di Filament.
    Notification::assertNotSentTo($user, ResetPasswordLink::class);
    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification): bool {
        return str_contains((string) $notification->url, '/manage/password-reset/reset');
    });
});

// --- email verification --------------------------------------------------------

it('redirects an authenticated but unverified member to the email verification prompt', function () {
    $organization = Organization::factory()->verified()->create();
    $user = User::factory()->unverified()->create();

    app(AddOrganizationMember::class)->handle(
        organization: $organization,
        user: $user,
        role: MembershipRole::Owner,
        verified: true,
    );

    // Precondizione: membership d'ente verificata ma email NON verificata.
    expect($user->isVerifiedMemberOf($organization))->toBeTrue()
        ->and($user->hasVerifiedEmail())->toBeFalse();

    // Il middleware `verified` (abilitato da emailVerification()) devia le
    // rotte protette del pannello verso la prompt di verifica email.
    $this->actingAs($user)
        ->get("/manage/{$organization->slug}")
        ->assertRedirect(route('filament.shelter.auth.email-verification.prompt'));
});

it('lets an authenticated member reach the panel once the email is verified', function () {
    $organization = Organization::factory()->verified()->create();
    $user = User::factory()->unverified()->create();

    app(AddOrganizationMember::class)->handle(
        organization: $organization,
        user: $user,
        role: MembershipRole::Owner,
        verified: true,
    );

    // Verifica l'email (equivalente al click sul link firmato di verifica).
    $user->markEmailAsVerified();

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();

    $this->actingAs($user)
        ->get("/manage/{$organization->slug}")
        ->assertSuccessful();
});

it('lets a verified authenticated user without membership reach the tenant registration page', function () {
    // BLINDA IL FIX di access-model: un utente autenticato con email VERIFICATA
    // e SENZA alcuna membership DEVE poter raggiungere la tenant registration
    // page (`/manage/new`) per creare/entrare nel proprio primo ente. Con il gate
    // di pannello rotto (canAccessPanel gated sulla membership) questa rotta dava
    // 403, rendendo la self-registration via HTTP irraggiungibile. Ora il gate di
    // pannello è permissivo e l'isolamento vive a livello di tenant.
    $user = User::factory()->create();

    // Precondizione: email verificata e nessuna membership.
    expect($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->organizations()->exists())->toBeFalse();

    $this->actingAs($user)
        ->get(route('filament.shelter.tenant.registration'))
        ->assertSuccessful();
});

it('blocks an unverified authenticated user from the tenant registration page', function () {
    // Invariante di sicurezza: la creazione di un ente (self-registration del
    // tenant) è gated dal middleware `verified`. Un utente non verificato non
    // deve poter creare un ente né operare finché non verifica l'email: viene
    // rediretto alla prompt di verifica anche su `/manage/new`.
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('filament.shelter.tenant.registration'))
        ->assertRedirect(route('filament.shelter.auth.email-verification.prompt'));
});
