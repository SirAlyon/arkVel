<?php

declare(strict_types=1);

use App\Exceptions\CalendarProviderNotConfigured;
use App\Support\Calendar\Google\GoogleOAuth;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    // Nessuna richiesta reale deve mai partire in questi test.
    Http::preventStrayRequests();

    config()->set('services.google_calendar.client_id', 'client-abc');
    config()->set('services.google_calendar.client_secret', 'secret-xyz');
    config()->set('services.google_calendar.redirect', 'https://arkvel.test/calendar/google/callback');
});

it('builds an authorization url with the right scope, offline access, consent prompt and state', function () {
    $url = app(GoogleOAuth::class)->authorizationUrl('state-123');

    expect($url)->toStartWith('https://accounts.google.com/o/oauth2/v2/auth?');

    // Estraggo la query string per asserire i singoli parametri (decodificati).
    $query = [];
    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect($query['client_id'])->toBe('client-abc')
        ->and($query['redirect_uri'])->toBe('https://arkvel.test/calendar/google/callback')
        ->and($query['response_type'])->toBe('code')
        ->and($query['scope'])->toBe('https://www.googleapis.com/auth/calendar.events')
        ->and($query['access_type'])->toBe('offline')
        ->and($query['prompt'])->toBe('consent')
        ->and($query['state'])->toBe('state-123');
});

it('exchanges an authorization code for tokens', function () {
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'access-1',
            'refresh_token' => 'refresh-1',
            'expires_in' => 3600,
            'scope' => 'https://www.googleapis.com/auth/calendar.events',
            'token_type' => 'Bearer',
        ]),
    ]);

    $tokens = app(GoogleOAuth::class)->exchangeCode('the-code');

    expect($tokens['access_token'])->toBe('access-1')
        ->and($tokens['refresh_token'])->toBe('refresh-1')
        ->and($tokens['expires_in'])->toBe(3600)
        ->and($tokens['scope'])->toBe('https://www.googleapis.com/auth/calendar.events');

    Http::assertSent(function (Request $request): bool {
        $body = $request->data();

        return $request->url() === 'https://oauth2.googleapis.com/token'
            && $body['grant_type'] === 'authorization_code'
            && $body['code'] === 'the-code'
            && $body['client_id'] === 'client-abc'
            && $body['client_secret'] === 'secret-xyz';
    });
});

it('refreshes an access token from a refresh token', function () {
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'access-2',
            'expires_in' => 3599,
            'scope' => 'https://www.googleapis.com/auth/calendar.events',
            'token_type' => 'Bearer',
        ]),
    ]);

    $tokens = app(GoogleOAuth::class)->refresh('refresh-1');

    expect($tokens['access_token'])->toBe('access-2')
        ->and($tokens['expires_in'])->toBe(3599);

    Http::assertSent(function (Request $request): bool {
        $body = $request->data();

        return $body['grant_type'] === 'refresh_token'
            && $body['refresh_token'] === 'refresh-1';
    });
});

it('normalizes a refresh response omitting the refresh token to null (caller preserves it)', function () {
    Http::fake([
        'oauth2.googleapis.com/token' => Http::response([
            'access_token' => 'access-3',
            'expires_in' => 3600,
            // Google spesso NON restituisce un nuovo refresh_token sul refresh.
        ]),
    ]);

    $tokens = app(GoogleOAuth::class)->refresh('refresh-1');

    expect($tokens['refresh_token'])->toBeNull()
        ->and($tokens['access_token'])->toBe('access-3');
});

it('throws CalendarProviderNotConfigured when credentials are missing', function () {
    config()->set('services.google_calendar.client_id', null);
    config()->set('services.google_calendar.client_secret', null);

    expect(fn () => app(GoogleOAuth::class)->authorizationUrl('state'))
        ->toThrow(CalendarProviderNotConfigured::class);
});

it('reports configured() based on client id and secret presence', function () {
    expect(GoogleOAuth::configured())->toBeTrue();

    config()->set('services.google_calendar.client_secret', null);
    expect(GoogleOAuth::configured())->toBeFalse();
});
