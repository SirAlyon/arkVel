<?php

declare(strict_types=1);

use App\Actions\Auth\AuthenticateUser;
use App\Data\Auth\LoginData;
use App\Enums\MembershipRole;
use App\Exceptions\AlreadyAuthenticated;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function login(string $email, string $password, bool $remember = false, string $ip = '203.0.113.5'): string
{
    return app(AuthenticateUser::class)->handle(new LoginData($email, $password, $remember), $ip, app('session.store'));
}

it('authenticates on the web guard and returns the adopter area by default', function (): void {
    $user = User::factory()->create(['password' => 'secret-password']);

    expect(login($user->email, 'secret-password'))->toBe(route('filament.adopter.home'))
        ->and(Auth::guard('web')->user()?->is($user))->toBeTrue();
});

it('regenerates the session and consumes the intended URL', function (): void {
    $user = User::factory()->create(['password' => 'secret-password']);
    $session = app('session.store');
    $session->put('url.intended', '/animali/3');
    $id = $session->getId();

    expect(login($user->email, 'secret-password'))->toBe('/animali/3')
        ->and($session->getId())->not->toBe($id)
        ->and($session->has('url.intended'))->toBeFalse();
});

it('fails with a generic message', function (): void {
    login('nobody@example.com', 'whatever');
})->throws(ValidationException::class, 'Email o password non corretti.');

it('counts failures per email and IP and clears them on success', function (): void {
    $user = User::factory()->create(['password' => 'secret-password']);

    foreach (range(1, AuthenticateUser::MAX_ATTEMPTS - 1) as $attempt) {
        try {
            login($user->email, 'wrong');
        } catch (ValidationException) {
        }
    }

    // L'ultimo tentativo disponibile riesce e azzera il contatore…
    login($user->email, 'secret-password');
    Auth::guard('web')->logout();

    // …quindi altri MAX_ATTEMPTS - 1 errori non bloccano ancora.
    foreach (range(1, AuthenticateUser::MAX_ATTEMPTS - 1) as $attempt) {
        try {
            login($user->email, 'wrong');
        } catch (ValidationException) {
        }
    }

    expect(login($user->email, 'secret-password'))->toBe(route('filament.adopter.home'));
});

it('throttles the same email from the same IP but not from another IP', function (): void {
    $user = User::factory()->create(['password' => 'secret-password']);

    foreach (range(1, AuthenticateUser::MAX_ATTEMPTS) as $attempt) {
        try {
            login(strtoupper($user->email), 'wrong');
        } catch (ValidationException) {
        }
    }

    expect(fn () => login($user->email, 'secret-password'))
        ->toThrow(ValidationException::class, 'Troppi tentativi');

    expect(login($user->email, 'secret-password', ip: '198.51.100.9'))->toBe(route('filament.adopter.home'));
});

it('limits all attempts from one IP whatever the email (password spraying)', function (): void {
    $user = User::factory()->create(['password' => 'secret-password']);

    foreach (range(1, AuthenticateUser::MAX_ATTEMPTS_PER_IP) as $attempt) {
        try {
            login("persona{$attempt}@example.com", 'Password1');
        } catch (ValidationException) {
        }
    }

    // Una sola prova per account, ma dallo stesso IP: anche le credenziali giuste aspettano.
    expect(fn () => login($user->email, 'secret-password'))
        ->toThrow(ValidationException::class, 'Troppi tentativi');
    expect(Auth::guard('web')->check())->toBeFalse();

    // Da un altro IP si entra.
    expect(login($user->email, 'secret-password', ip: '198.51.100.9'))->toBe(route('filament.adopter.home'));
});

it('counts successful sign-ins in the IP limit too', function (): void {
    $user = User::factory()->create(['password' => 'secret-password']);

    foreach (range(1, AuthenticateUser::MAX_ATTEMPTS_PER_IP) as $attempt) {
        login($user->email, 'secret-password');
        Auth::guard('web')->logout();
    }

    expect(fn () => login($user->email, 'secret-password'))
        ->toThrow(ValidationException::class, 'Troppi tentativi');

    $this->travel(AuthenticateUser::IP_DECAY_SECONDS + 1)->seconds();
    expect(login($user->email, 'secret-password'))->toBe(route('filament.adopter.home'));
});

it('sends a member of an organization to /manage by default', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $member = member(Organization::factory()->verified()->create(), MembershipRole::Volunteer);
    $member->forceFill(['password' => 'secret-password'])->save();

    expect(login($member->email, 'secret-password'))->toBe(route('filament.shelter.tenant'));
});

it('does nothing for a session that is already signed in', function (): void {
    $user = User::factory()->create(['password' => 'secret-password']);
    $this->actingAs($user);

    try {
        login('someone-else@example.com', 'whatever');
        $this->fail('Expected AlreadyAuthenticated');
    } catch (AlreadyAuthenticated $exception) {
        expect($exception->destination)->toBe(route('filament.adopter.home'));
    }
});
