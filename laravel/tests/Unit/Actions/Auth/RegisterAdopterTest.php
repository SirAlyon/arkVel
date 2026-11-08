<?php

declare(strict_types=1);

use App\Actions\Auth\RegisterAdopter;
use App\Exceptions\AlreadyAuthenticated;
use App\Models\User;
use App\Support\Auth\Honeypot;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Event::fake([Registered::class]);
});

/**
 * Registrazione con input grezzo valido, come lo manda il modulo, e un modulo comparso
 * abbastanza tempo prima da superare il honeypot.
 *
 * @param  array<string, mixed>  $overrides
 */
function registerAdopter(string $email, string $ip = '203.0.113.5', array $overrides = []): User
{
    return app(RegisterAdopter::class)->handle(
        [
            'name' => 'Giulia',
            'email' => $email,
            'password' => 'una-password-lunga',
            'password_confirmation' => 'una-password-lunga',
            'privacy' => true,
            Honeypot::FIELD => '',
            ...$overrides,
        ],
        now()->getTimestamp() - Honeypot::MIN_SECONDS,
        $ip,
        app('session.store'),
    );
}

/** Registra e chiude subito la sessione, per poter registrare di nuovo. */
function registerAndSignOut(string $email, string $ip = '203.0.113.5'): User
{
    $user = registerAdopter($email, $ip);
    Auth::guard('web')->logout();

    return $user;
}

it('creates the user with a hashed password, logs in and regenerates the session', function (): void {
    $session = app('session.store');
    $id = $session->getId();

    $user = registerAdopter('giulia@example.com');

    expect(Hash::check('una-password-lunga', $user->password))->toBeTrue()
        ->and($user->email_verified_at)->toBeNull()
        ->and(Auth::guard('web')->user()?->is($user))->toBeTrue()
        ->and($session->getId())->not->toBe($id);

    Event::assertDispatched(Registered::class);
});

it('logs the privacy notice acknowledgement with the policy version, in the user locale', function (): void {
    app()->setLocale('en');
    config(['arkvel.privacy_policy_version' => '2027-01']);

    $user = registerAdopter('giulia@example.com');

    $activity = Activity::query()->sole();
    expect($activity->description)->toBe(RegisterAdopter::PRIVACY_NOTICE_LOG)
        ->and($activity->subject_id)->toBe($user->getKey())
        ->and($activity->causer_id)->toBe($user->getKey())
        ->and($activity->properties->all())->toBe(['notice' => 'privacy_policy', 'policy_version' => '2027-01', 'locale' => 'en']);
});

it('validates the raw input itself', function (): void {
    expect(fn () => registerAdopter('not-an-email', overrides: ['privacy' => false]))
        ->toThrow(ValidationException::class);
    expect(User::query()->count())->toBe(0);
});

it('normalizes the email before validating and storing it', function (): void {
    expect(registerAdopter('  Giulia@Example.COM ')->email)->toBe('giulia@example.com');
});

it('rejects the honeypot field and a too fast submission', function (array $overrides, int $renderedAgo): void {
    $attempt = fn () => app(RegisterAdopter::class)->handle(
        [
            'name' => 'Bot',
            'email' => 'bot@example.com',
            'password' => 'una-password-lunga',
            'password_confirmation' => 'una-password-lunga',
            'privacy' => true,
            ...$overrides,
        ],
        now()->getTimestamp() - $renderedAgo,
        '203.0.113.5',
        app('session.store'),
    );

    expect($attempt)->toThrow(ValidationException::class, 'Non siamo riusciti a completare la registrazione.');
    expect(User::query()->count())->toBe(0);
})->with([
    'filled trap' => [[Honeypot::FIELD => 'https://spam.example'], Honeypot::MIN_SECONDS + 5],
    'non-string trap' => [[Honeypot::FIELD => ['x']], Honeypot::MIN_SECONDS + 5],
    'too fast' => [[], 0],
]);

it('rolls back the user when a step of the registration fails', function (): void {
    Activity::creating(static function (): never {
        throw new RuntimeException('activitylog down');
    });

    expect(fn () => registerAdopter('giulia@example.com'))->toThrow(RuntimeException::class);
    expect(User::query()->count())->toBe(0);
    Event::assertNotDispatched(Registered::class);
});

it('turns a concurrent registration with the same email into a validation error', function (): void {
    // L'altra richiesta crea l'account dopo la validazione di questa, prima dell'insert
    // (nel test la riga vive nella stessa transazione, quindi sparisce col rollback).
    User::creating(static function (User $user): void {
        if ($user->email === 'giulia@example.com' && ! DB::table('users')->where('email', 'giulia@example.com')->exists()) {
            DB::table('users')->insert([
                'name' => 'Altra richiesta',
                'email' => 'giulia@example.com',
                'password' => 'x',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    });

    try {
        registerAdopter('giulia@example.com');
        $this->fail('Expected a ValidationException');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe(['email' => ['Esiste già un account con questo indirizzo email. Accedi o reimposta la password.']]);
    }

    expect(Auth::guard('web')->check())->toBeFalse()
        ->and(Activity::query()->count())->toBe(0);
    Event::assertNotDispatched(Registered::class);
});

it('limits successful sign-ups per IP', function (): void {
    foreach (range(1, RegisterAdopter::MAX_REGISTRATIONS) as $index) {
        registerAndSignOut("persona{$index}@example.com");
        $this->travel(RegisterAdopter::SUBMISSIONS_DECAY_SECONDS)->seconds();
    }

    expect(fn () => registerAdopter('extra@example.com'))->toThrow(ValidationException::class, 'Troppi tentativi');
    expect(registerAdopter('altro-ip@example.com', '198.51.100.9')->exists)->toBeTrue();

    Auth::guard('web')->logout();
    $this->travel(RegisterAdopter::REGISTRATIONS_DECAY_SECONDS)->seconds();
    expect(registerAdopter('dopo-un-ora@example.com')->exists)->toBeTrue();
});

it('limits every submission per IP before validating, in minutes once the wait is long', function (): void {
    foreach (range(1, RegisterAdopter::MAX_SUBMISSIONS) as $attempt) {
        try {
            registerAdopter('not-an-email');
        } catch (ValidationException) {
        }
    }

    try {
        registerAdopter('valida@example.com');
        $this->fail('Expected a ValidationException');
    } catch (ValidationException $exception) {
        expect(array_keys($exception->errors()))->toBe(['form'])
            ->and($exception->errors()['form'][0])->toStartWith('Troppi tentativi. Riprova tra');
    }

    $this->travel(RegisterAdopter::SUBMISSIONS_DECAY_SECONDS + 1)->seconds();
    expect(registerAdopter('valida@example.com')->exists)->toBeTrue();
});

it('does nothing for a session that is already signed in', function (): void {
    $this->actingAs(User::factory()->create());

    expect(fn () => registerAdopter('giulia@example.com'))->toThrow(AlreadyAuthenticated::class);
    expect(User::query()->where('email', 'giulia@example.com')->exists())->toBeFalse();
});
