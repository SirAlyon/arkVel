<?php

declare(strict_types=1);

use App\Models\CalendarConnection;
use App\Support\Calendar\Google\GoogleTokenManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();

    config()->set('services.google_calendar.client_id', 'client-abc');
    config()->set('services.google_calendar.client_secret', 'secret-xyz');
});

it('returns the stored access token when it is still fresh (no refresh call)', function () {
    // Nessun fake: se provasse a rinnovare, preventStrayRequests farebbe fallire il test.
    $connection = CalendarConnection::factory()->google()->create([
        'credentials' => [
            'access_token' => 'still-fresh',
            'refresh_token' => 'refresh-1',
            'expires_at' => now()->addHour()->toIso8601String(),
        ],
    ]);

    $token = app(GoogleTokenManager::class)->accessTokenFor($connection);

    expect($token)->toBe('still-fresh');
    Http::assertNothingSent();
});

it('refreshes and persists (encrypted) the new access token when expired, preserving the refresh token', function () {
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'renewed-access',
            'expires_in' => 3600,
            // Google omette il refresh_token: va preservato quello esistente.
        ]),
    ]);

    $connection = CalendarConnection::factory()->google()->create([
        'credentials' => [
            'access_token' => 'expired-access',
            'refresh_token' => 'refresh-keepme',
            'expires_at' => now()->subMinute()->toIso8601String(),
        ],
    ]);

    $token = app(GoogleTokenManager::class)->accessTokenFor($connection);

    expect($token)->toBe('renewed-access');

    $connection->refresh();
    expect($connection->credentials['access_token'])->toBe('renewed-access')
        ->and($connection->credentials['refresh_token'])->toBe('refresh-keepme');

    // Persistito cifrato: la colonna grezza non contiene il token in chiaro.
    $raw = $connection->getRawOriginal('credentials');
    expect($raw)->not->toContain('renewed-access')
        ->and($raw)->not->toContain('refresh-keepme');
});

it('refreshes when there is no expiry recorded (cannot trust the token)', function () {
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'renewed-noexpiry',
            'expires_in' => 3600,
        ]),
    ]);

    $connection = CalendarConnection::factory()->google()->create([
        'credentials' => [
            'access_token' => 'some-token',
            'refresh_token' => 'refresh-1',
            // nessuna expires_at
        ],
    ]);

    expect(app(GoogleTokenManager::class)->accessTokenFor($connection))->toBe('renewed-noexpiry');
});

it('throws when there is no refresh token to renew with (reconnect required)', function () {
    $connection = CalendarConnection::factory()->google()->create([
        'credentials' => [
            'access_token' => 'expired',
            'refresh_token' => null,
            'expires_at' => now()->subMinute()->toIso8601String(),
        ],
    ]);

    expect(fn () => app(GoogleTokenManager::class)->accessTokenFor($connection))
        ->toThrow(RuntimeException::class);

    Http::assertNothingSent();
});
