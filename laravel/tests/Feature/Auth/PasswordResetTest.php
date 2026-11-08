<?php

declare(strict_types=1);

use App\Actions\Auth\ResetPassword;
use App\Actions\Auth\SendPasswordResetLink;
use App\Livewire\Auth\ForgotPasswordPage;
use App\Livewire\Auth\ResetPasswordPage;
use App\Models\User;
use App\Notifications\Auth\ResetPasswordLink;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
 | Password dimenticata (/password-dimenticata, `password.request`) e reimpostazione
 | (/reimposta-password/{token}, `password.reset`), UI-5. Logica in
 | App\Actions\Auth\SendPasswordResetLink e App\Actions\Auth\ResetPassword.
 */

// --- richiesta del link ---------------------------------------------------------

it('renders the forgotten password page with noindex', function (): void {
    $this->get(route('password.request'))
        ->assertOk()
        ->assertSeeLivewire(ForgotPasswordPage::class)
        ->assertSee('<title>Password dimenticata — ArkVel</title>', false)
        ->assertSee('<meta name="robots" content="noindex, follow">', false);
});

it('sends the queued reset mail with the public link', function (): void {
    Notification::fake();
    $user = User::factory()->create();

    Livewire::test(ForgotPasswordPage::class)
        ->set('email', $user->email)
        ->call('sendLink')
        ->assertHasNoErrors()
        ->assertSet('sent', true)
        // Niente toast: la conferma è il blocco che prende il focus (letto per intero).
        ->assertNotDispatched('toast')
        ->assertSee('Se esiste un account con questo indirizzo')
        ->assertSee('Il link vale 60 minuti.')
        ->assertSeeHtml('id="forgot-sent"')
        ->assertSeeHtml('tabindex="-1"')
        ->assertSeeHtml('aria-labelledby="forgot-sent-title"')
        ->assertSeeHtml('aria-describedby="forgot-sent-description"')
        ->assertSeeHtml('id="forgot-sent-title"')
        ->assertSeeHtml('id="forgot-sent-description"');

    Notification::assertSentTo($user, ResetPasswordLink::class, function (ResetPasswordLink $notification) use ($user): bool {
        $url = $notification->toMail($user)->actionUrl;

        return str_starts_with($url, url('/reimposta-password/'.$notification->token))
            && str_contains($url, 'email='.urlencode($user->email));
    });
    expect(new ResetPasswordLink('token'))->toBeInstanceOf(ShouldQueue::class);
});

it('answers the same way for an unknown email, without sending anything', function (): void {
    Notification::fake();

    Livewire::test(ForgotPasswordPage::class)
        ->set('email', 'nessuno@example.com')
        ->call('sendLink')
        ->assertHasNoErrors()
        ->assertSet('sent', true)
        ->assertSee('Se esiste un account con questo indirizzo');

    Notification::assertNothingSent();
});

it('answers the same way when the broker throttles a repeated request', function (): void {
    Notification::fake();
    $user = User::factory()->create();

    foreach ([1, 2] as $attempt) {
        Livewire::test(ForgotPasswordPage::class)
            ->set('email', $user->email)
            ->call('sendLink')
            ->assertHasNoErrors()
            ->assertSet('sent', true);
    }

    Notification::assertSentToTimes($user, ResetPasswordLink::class, 1);
});

it('validates the email address', function (): void {
    Livewire::test(ForgotPasswordPage::class)
        ->set('email', 'non-valida')
        ->call('sendLink')
        ->assertHasErrors(['email'])
        ->assertSet('sent', false);
});

it('limits the reset requests from the same IP', function (): void {
    Notification::fake();

    foreach (range(1, SendPasswordResetLink::MAX_ATTEMPTS) as $index) {
        Livewire::test(ForgotPasswordPage::class)
            ->set('email', "persona{$index}@example.com")
            ->call('sendLink')
            ->assertHasNoErrors();
    }

    Livewire::test(ForgotPasswordPage::class)
        ->set('email', 'altra@example.com')
        ->call('sendLink')
        ->assertHasErrors(['email'])
        ->assertSet('sent', false)
        ->assertSee('Troppi tentativi');
});

// --- reimpostazione -------------------------------------------------------------

it('renders the reset page with the email from the link and a canonical without the token', function (): void {
    $this->get(route('password.reset', ['token' => 'segreto-123', 'email' => 'mario@example.com']))
        ->assertOk()
        ->assertSeeLivewire(ResetPasswordPage::class)
        ->assertSee('<link rel="canonical" href="'.route('password.request').'">', false)
        ->assertSee('<meta property="og:url" content="'.route('password.request').'">', false)
        ->assertSee('<meta name="robots" content="noindex, follow">', false);

    Livewire::withQueryParams(['email' => 'mario@example.com'])
        ->test(ResetPasswordPage::class, ['token' => 'segreto-123'])
        ->assertSet('email', 'mario@example.com')
        ->assertSet('token', 'segreto-123');
});

it('resets the password, rotates the remember token and goes to sign in', function (): void {
    Event::fake([PasswordReset::class]);
    $user = User::factory()->create(['remember_token' => 'vecchio-token']);
    $token = Password::broker()->createToken($user);
    $sessionId = session()->getId();

    Livewire::test(ResetPasswordPage::class, ['token' => $token])
        ->set('email', $user->email)
        ->set('password', 'nuova-password-lunga')
        ->set('password_confirmation', 'nuova-password-lunga')
        ->call('resetPassword')
        ->assertHasNoErrors()
        ->assertRedirect(route('login'));

    $user->refresh();
    expect(Hash::check('nuova-password-lunga', $user->password))->toBeTrue()
        ->and($user->remember_token)->not->toBe('vecchio-token')
        ->and(session('toast'))->toBe(['type' => 'success', 'title' => 'Password aggiornata: ora puoi accedere.'])
        ->and(session()->getId())->not->toBe($sessionId);

    Event::assertDispatched(PasswordReset::class, fn (PasswordReset $event): bool => $event->user->is($user));
    // Come il reset di Filament: nessun accesso automatico.
    $this->assertGuest();
});

it('rejects an invalid token and an unknown email with the same form error and a way to ask a new link', function (string $email): void {
    $user = User::factory()->create(['email' => 'mario@example.com', 'password' => 'vecchia-password']);
    Password::broker()->createToken($user);

    Livewire::test(ResetPasswordPage::class, ['token' => 'token-sbagliato'])
        ->set('email', $email)
        ->set('password', 'nuova-password-lunga')
        ->set('password_confirmation', 'nuova-password-lunga')
        ->call('resetPassword')
        ->assertHasErrors(['form'])
        ->assertHasNoErrors(['email'])
        ->assertSee('Il link non è valido o è scaduto.')
        ->assertSeeHtml('data-auth-request-new')
        ->assertSeeHtml('href="'.route('password.request').'"')
        ->assertSet('password', '')
        ->assertSet('password_confirmation', '')
        ->assertNoRedirect();

    expect(Hash::check('vecchia-password', $user->fresh()->password))->toBeTrue();
})->with(['wrong token' => 'mario@example.com', 'unknown email' => 'nessuno@example.com']);

it('validates the new password', function (): void {
    Livewire::test(ResetPasswordPage::class, ['token' => 'qualcosa'])
        ->set('email', 'mario@example.com')
        ->set('password', 'corta')
        ->set('password_confirmation', 'diversa')
        ->call('resetPassword')
        ->assertHasErrors(['password'])
        ->assertSee('La password deve avere almeno 8 caratteri.')
        ->assertDontSeeHtml('data-auth-request-new')
        ->assertSet('password', '');
});

it('locks the token from the client', function (): void {
    Livewire::test(ResetPasswordPage::class, ['token' => 'qualcosa'])->set('token', 'altro');
})->throws(CannotUpdateLockedPropertyException::class);

it('limits the reset attempts for the same email and IP', function (): void {
    foreach (range(1, ResetPassword::MAX_ATTEMPTS) as $attempt) {
        Livewire::test(ResetPasswordPage::class, ['token' => 'sbagliato'])
            ->set('email', 'mario@example.com')
            ->set('password', 'nuova-password-lunga')
            ->set('password_confirmation', 'nuova-password-lunga')
            ->call('resetPassword')
            ->assertSee('Il link non è valido o è scaduto.');
    }

    Livewire::test(ResetPasswordPage::class, ['token' => 'sbagliato'])
        ->set('email', 'mario@example.com')
        ->set('password', 'nuova-password-lunga')
        ->set('password_confirmation', 'nuova-password-lunga')
        ->call('resetPassword')
        ->assertHasErrors(['form'])
        ->assertSee('Troppi tentativi');
});

it('suggests the account email to password managers', function (): void {
    $this->get(route('password.reset', ['token' => 'x', 'email' => 'mario@example.com']))
        ->assertSee('autocomplete="username"', false)
        ->assertSee('Salva password');
});

it('resets with the email in another case', function (): void {
    $user = User::factory()->create(['email' => 'mario@example.com']);
    $token = Password::broker()->createToken($user);

    Livewire::test(ResetPasswordPage::class, ['token' => $token])
        ->set('email', ' Mario@Example.com')
        ->set('password', 'nuova-password-lunga')
        ->set('password_confirmation', 'nuova-password-lunga')
        ->call('resetPassword')
        ->assertHasNoErrors()
        ->assertRedirect(route('login'));
});

it('does not touch the password pages\' actions when the session signed in meanwhile', function (): void {
    Notification::fake();
    $user = User::factory()->create(['password' => 'vecchia-password']);
    $token = Password::broker()->createToken($user);

    $forgot = Livewire::test(ForgotPasswordPage::class)->set('email', $user->email);
    $reset = Livewire::test(ResetPasswordPage::class, ['token' => $token])
        ->set('email', $user->email)
        ->set('password', 'nuova-password-lunga')
        ->set('password_confirmation', 'nuova-password-lunga');

    $this->actingAs($user);

    $forgot->call('sendLink')->assertRedirect(route('filament.adopter.home'))->assertSet('sent', false);
    $reset->call('resetPassword')->assertRedirect(route('filament.adopter.home'));

    Notification::assertNothingSent();
    expect(Hash::check('vecchia-password', $user->fresh()->password))->toBeTrue();
});

it('sends signed-in visitors away from the password pages', function (string $route, array $parameters): void {
    $this->actingAs(User::factory()->create())
        ->get(route($route, $parameters))
        ->assertRedirect(route('filament.adopter.home'));
})->with([
    'request' => ['password.request', []],
    'reset' => ['password.reset', ['token' => 'x']],
]);
