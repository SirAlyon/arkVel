<?php

declare(strict_types=1);

use App\Support\Calendar\Google\GoogleCalendarConfig;
use App\Support\Settings\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;

// Il resolver legge lo store (DB), quindi serve lo schema migrato.
uses(RefreshDatabase::class);

beforeEach(function () {
    // Parte da .env vuoto (come in CI): ogni test decide cosa impostare.
    config()->set('services.google_calendar.client_id', null);
    config()->set('services.google_calendar.client_secret', null);
    config()->set('services.google_calendar.redirect', null);
});

function config_resolver(): GoogleCalendarConfig
{
    return app(GoogleCalendarConfig::class);
}

it('returns the .env values when the DB has no override', function () {
    config()->set('services.google_calendar.client_id', 'env-client');
    config()->set('services.google_calendar.client_secret', 'env-secret');
    config()->set('services.google_calendar.redirect', 'https://env.test/callback');

    $config = config_resolver();

    expect($config->clientId())->toBe('env-client')
        ->and($config->clientSecret())->toBe('env-secret')
        ->and($config->redirectUri())->toBe('https://env.test/callback');
});

it('lets DB values override the .env', function () {
    config()->set('services.google_calendar.client_id', 'env-client');
    config()->set('services.google_calendar.client_secret', 'env-secret');

    $settings = app(PlatformSettings::class);
    $settings->set(GoogleCalendarConfig::KEY_CLIENT_ID, 'db-client');
    $settings->set(GoogleCalendarConfig::KEY_CLIENT_SECRET, 'db-secret');
    $settings->set(GoogleCalendarConfig::KEY_REDIRECT_URI, 'https://db.test/callback');

    $config = config_resolver();

    expect($config->clientId())->toBe('db-client')
        ->and($config->clientSecret())->toBe('db-secret')
        ->and($config->redirectUri())->toBe('https://db.test/callback');
});

it('falls back to .env per-key when only some DB keys are set', function () {
    config()->set('services.google_calendar.client_id', 'env-client');
    config()->set('services.google_calendar.client_secret', 'env-secret');

    // Solo il secret è sovrascritto da DB; il client_id ricade sull'.env.
    app(PlatformSettings::class)->set(GoogleCalendarConfig::KEY_CLIENT_SECRET, 'db-secret');

    $config = config_resolver();

    expect($config->clientId())->toBe('env-client')
        ->and($config->clientSecret())->toBe('db-secret');
});

it('is configured when the DB has both credentials even if the .env is empty', function () {
    $settings = app(PlatformSettings::class);
    $settings->set(GoogleCalendarConfig::KEY_CLIENT_ID, 'db-client');
    $settings->set(GoogleCalendarConfig::KEY_CLIENT_SECRET, 'db-secret');

    expect(config_resolver()->configured())->toBeTrue();
});

it('is not configured when neither DB nor .env provide credentials', function () {
    expect(config_resolver()->configured())->toBeFalse()
        ->and(config_resolver()->clientId())->toBeNull()
        ->and(config_resolver()->clientSecret())->toBeNull();
});

it('reports source() as db, env or none', function () {
    // none: niente configurato.
    expect(config_resolver()->source())->toBe('none');

    // env: solo .env.
    config()->set('services.google_calendar.client_id', 'env-client');
    config()->set('services.google_calendar.client_secret', 'env-secret');
    expect(config_resolver()->source())->toBe('env');

    // db: almeno una chiave arriva dal DB (override attivo).
    app(PlatformSettings::class)->set(GoogleCalendarConfig::KEY_CLIENT_ID, 'db-client');
    expect(config_resolver()->source())->toBe('db');
});
