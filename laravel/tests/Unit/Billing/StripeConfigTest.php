<?php

declare(strict_types=1);

use App\Providers\BillingServiceProvider;
use App\Support\Billing\StripeConfig;
use App\Support\Settings\PlatformSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

// Il resolver legge lo store (DB), quindi serve lo schema migrato.
uses(RefreshDatabase::class);

beforeEach(function () {
    // Parte da .env vuoto (come in CI/phpunit.xml): ogni test decide cosa impostare.
    config()->set('cashier.key', null);
    config()->set('cashier.secret', null);
    config()->set('cashier.webhook.secret', null);
});

function stripe_resolver(): StripeConfig
{
    return app(StripeConfig::class);
}

it('returns the .env (cashier config) values when the DB has no override', function () {
    config()->set('cashier.key', 'pk_env');
    config()->set('cashier.secret', 'sk_env');
    config()->set('cashier.webhook.secret', 'whsec_env');

    $config = stripe_resolver();

    expect($config->publishableKey())->toBe('pk_env')
        ->and($config->secretKey())->toBe('sk_env')
        ->and($config->webhookSecret())->toBe('whsec_env');
});

it('lets DB values override the .env per key', function () {
    config()->set('cashier.key', 'pk_env');
    config()->set('cashier.secret', 'sk_env');
    config()->set('cashier.webhook.secret', 'whsec_env');

    $settings = app(PlatformSettings::class);
    $settings->set(StripeConfig::KEY_PUBLISHABLE, 'pk_db');
    $settings->set(StripeConfig::KEY_SECRET, 'sk_db');
    $settings->set(StripeConfig::KEY_WEBHOOK_SECRET, 'whsec_db');

    $config = stripe_resolver();

    expect($config->publishableKey())->toBe('pk_db')
        ->and($config->secretKey())->toBe('sk_db')
        ->and($config->webhookSecret())->toBe('whsec_db');
});

it('falls back to .env per-key when only some DB keys are set', function () {
    config()->set('cashier.key', 'pk_env');
    config()->set('cashier.secret', 'sk_env');

    // Solo il secret è sovrascritto da DB; la publishable ricade sull'.env.
    app(PlatformSettings::class)->set(StripeConfig::KEY_SECRET, 'sk_db');

    $config = stripe_resolver();

    expect($config->publishableKey())->toBe('pk_env')
        ->and($config->secretKey())->toBe('sk_db');
});

it('is configured when the DB has both credentials even if the .env is empty', function () {
    $settings = app(PlatformSettings::class);
    $settings->set(StripeConfig::KEY_PUBLISHABLE, 'pk_db');
    $settings->set(StripeConfig::KEY_SECRET, 'sk_db');

    expect(stripe_resolver()->configured())->toBeTrue();
});

it('is not configured when neither DB nor .env provide credentials', function () {
    expect(stripe_resolver()->configured())->toBeFalse()
        ->and(stripe_resolver()->publishableKey())->toBeNull()
        ->and(stripe_resolver()->secretKey())->toBeNull()
        ->and(stripe_resolver()->webhookSecret())->toBeNull();
});

it('is not configured when only one credential is present', function () {
    config()->set('cashier.key', 'pk_env');

    expect(stripe_resolver()->configured())->toBeFalse();
});

it('reports source() as db, env or none', function () {
    // none: niente configurato.
    expect(stripe_resolver()->source())->toBe('none');

    // env: solo .env.
    config()->set('cashier.key', 'pk_env');
    config()->set('cashier.secret', 'sk_env');
    expect(stripe_resolver()->source())->toBe('env');

    // db: almeno una chiave arriva dal DB (override attivo).
    app(PlatformSettings::class)->set(StripeConfig::KEY_PUBLISHABLE, 'pk_db');
    expect(stripe_resolver()->source())->toBe('db');
});

it('reports hasStoredSecretKey only when the secret is persisted in the DB', function () {
    expect(stripe_resolver()->hasStoredSecretKey())->toBeFalse();

    app(PlatformSettings::class)->set(StripeConfig::KEY_SECRET, 'sk_db');

    expect(stripe_resolver()->hasStoredSecretKey())->toBeTrue();
});

it('applies DB keys over the cashier config at runtime via the service provider', function () {
    config()->set('cashier.key', 'pk_env');
    config()->set('cashier.secret', 'sk_env');
    config()->set('cashier.webhook.secret', 'whsec_env');

    $settings = app(PlatformSettings::class);
    $settings->set(StripeConfig::KEY_PUBLISHABLE, 'pk_db');
    $settings->set(StripeConfig::KEY_SECRET, 'sk_db');
    $settings->set(StripeConfig::KEY_WEBHOOK_SECRET, 'whsec_db');

    // Rigioca il boot del provider con il DB popolato.
    (new BillingServiceProvider(app()))->boot();

    expect(config('cashier.key'))->toBe('pk_db')
        ->and(config('cashier.secret'))->toBe('sk_db')
        ->and(config('cashier.webhook.secret'))->toBe('whsec_db');
});

it('leaves the cashier config untouched when the DB has no override', function () {
    config()->set('cashier.key', 'pk_env');
    config()->set('cashier.secret', 'sk_env');

    (new BillingServiceProvider(app()))->boot();

    expect(config('cashier.key'))->toBe('pk_env')
        ->and(config('cashier.secret'))->toBe('sk_env');
});

it('degrades gracefully to the .env when the settings table is absent', function () {
    config()->set('cashier.key', 'pk_env');
    config()->set('cashier.secret', 'sk_env');

    // Simula il contesto pre-migrate rimuovendo la tabella: la lettura deve degradare
    // a "assente" invece di sollevare, così il resolver ricade sull'.env.
    Schema::drop('platform_settings');

    $config = stripe_resolver();

    expect($config->secretKey())->toBe('sk_env')
        ->and($config->configured())->toBeTrue()
        ->and($config->source())->toBe('env');

    // Il provider non deve sollevare in assenza della tabella.
    (new BillingServiceProvider(app()))->boot();

    expect(config('cashier.key'))->toBe('pk_env');
});
