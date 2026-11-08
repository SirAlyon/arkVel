<?php

declare(strict_types=1);

use App\Support\Auth\AuthRateLimiter;
use Illuminate\Validation\ValidationException;

it('builds hashed keys that never contain the email or the IP', function (): void {
    $key = AuthRateLimiter::key('login', 'mario@example.com', '203.0.113.5');

    expect($key)->toStartWith('auth-public:login:')
        ->not->toContain('mario')
        ->not->toContain('203.0.113.5')
        ->and(AuthRateLimiter::key('login', 'a', 'b'))->not->toBe(AuthRateLimiter::key('register', 'a', 'b'));
});

it('throws on the given field once the attempts are used, then clears', function (): void {
    $limiter = app(AuthRateLimiter::class);
    $key = AuthRateLimiter::key('test', 'x');

    $limiter->hit($key, 60);
    $limiter->ensureAvailable($key, 2, 'email');
    $limiter->hit($key, 60);

    try {
        $limiter->ensureAvailable($key, 2, 'email');
        $this->fail('Expected a ValidationException');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('email')
            ->and($exception->errors()['email'][0])->toMatch('/^Troppi tentativi\. Riprova tra \d+ second[oi]\.$/');
    }

    $limiter->clear($key);
    $limiter->ensureAvailable($key, 2, 'email');
});

it('uses the singular for one second left', function (): void {
    $this->freezeTime();
    $limiter = app(AuthRateLimiter::class);
    $key = AuthRateLimiter::key('test', 'singular');
    $limiter->hit($key, 1);

    expect($limiter->throttledMessage($key))->toBe('Troppi tentativi. Riprova tra 1 secondo.');
});

it('says the wait in minutes, rounded up, once it is longer than a minute', function (): void {
    $this->freezeTime();
    $limiter = app(AuthRateLimiter::class);
    $key = AuthRateLimiter::key('test', 'minutes');
    $limiter->hit($key, 600);

    expect($limiter->throttledMessage($key))->toBe('Troppi tentativi. Riprova tra 10 minuti.');

    $this->travel(530)->seconds();
    expect($limiter->throttledMessage($key))->toBe('Troppi tentativi. Riprova tra 2 minuti.');

    $this->travel(20)->seconds();
    expect($limiter->throttledMessage($key))->toBe('Troppi tentativi. Riprova tra 50 secondi.');
});

it('says the wait in English too', function (): void {
    $this->freezeTime();
    app()->setLocale('en');
    $limiter = app(AuthRateLimiter::class);
    $minutes = AuthRateLimiter::key('test', 'en-minutes');
    $second = AuthRateLimiter::key('test', 'en-second');
    $limiter->hit($minutes, 600);
    $limiter->hit($second, 1);

    expect($limiter->throttledMessage($minutes))->toBe('Too many attempts. Try again in 10 minutes.')
        ->and($limiter->throttledMessage($second))->toBe('Too many attempts. Try again in 1 second.');
});
