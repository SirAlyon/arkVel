<?php

declare(strict_types=1);

use App\Actions\Auth\ResetPassword;
use App\Data\Auth\ResetPasswordData;
use App\Exceptions\AlreadyAuthenticated;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function resetPassword(string $token, string $email, string $ip = '203.0.113.5'): void
{
    app(ResetPassword::class)->handle(new ResetPasswordData($token, $email, 'nuova-password-lunga'), $ip, app('session.store'));
}

it('saves the new password, rotates the remember token, deletes the token and fires PasswordReset', function (): void {
    Event::fake([PasswordReset::class]);
    $user = User::factory()->create(['remember_token' => 'old']);
    $token = Password::broker()->createToken($user);
    $session = app('session.store');
    $id = $session->getId();

    resetPassword($token, $user->email);

    $user->refresh();
    expect(Hash::check('nuova-password-lunga', $user->password))->toBeTrue()
        ->and($user->remember_token)->not->toBe('old')->toHaveLength(60)
        ->and(DB::table('password_reset_tokens')->count())->toBe(0)
        ->and($session->getId())->not->toBe($id)
        ->and(Auth::guard('web')->check())->toBeFalse();
    Event::assertDispatched(PasswordReset::class);
});

it('refuses an invalid token without changing anything', function (): void {
    $user = User::factory()->create(['password' => 'vecchia']);
    Password::broker()->createToken($user);

    expect(fn () => resetPassword('wrong', $user->email))
        ->toThrow(ValidationException::class, 'Il link non è valido o è scaduto.');
    expect(Hash::check('vecchia', $user->fresh()->password))->toBeTrue();
});

it('refuses an expired token', function (): void {
    $user = User::factory()->create();
    $token = Password::broker()->createToken($user);

    $this->travel(61)->minutes();

    resetPassword($token, $user->email);
})->throws(ValidationException::class, 'Il link non è valido o è scaduto.');

it('reports the failure on the form, not on the email field', function (): void {
    try {
        resetPassword('wrong', 'mario@example.com');
        $this->fail('Expected a ValidationException');
    } catch (ValidationException $exception) {
        expect(array_keys($exception->errors()))->toBe(['form']);
    }
});

it('does nothing for a session that is already signed in', function (): void {
    $user = User::factory()->create(['password' => 'vecchia']);
    $token = Password::broker()->createToken($user);
    $this->actingAs($user);

    expect(fn () => resetPassword($token, $user->email))->toThrow(AlreadyAuthenticated::class);
    expect(Hash::check('vecchia', $user->fresh()->password))->toBeTrue();
});

it('limits the attempts per email and IP and clears them on success', function (): void {
    $user = User::factory()->create();

    foreach (range(1, ResetPassword::MAX_ATTEMPTS - 1) as $attempt) {
        try {
            resetPassword('wrong', $user->email);
        } catch (ValidationException) {
        }
    }

    resetPassword(Password::broker()->createToken($user), $user->email);

    foreach (range(1, ResetPassword::MAX_ATTEMPTS) as $attempt) {
        try {
            resetPassword('wrong', $user->email);
        } catch (ValidationException) {
        }
    }

    expect(fn () => resetPassword(Password::broker()->createToken($user), $user->email))
        ->toThrow(ValidationException::class, 'Troppi tentativi');
});
