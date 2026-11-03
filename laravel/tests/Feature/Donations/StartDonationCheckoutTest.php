<?php

declare(strict_types=1);

use App\Actions\Donations\StartDonationCheckout;
use App\Enums\DonationStatus;
use App\Enums\DonationType;
use App\Exceptions\DonationsNotConfigured;
use App\Exceptions\InvalidDonationAmount;
use App\Exceptions\MissingDonorIdentity;
use App\Models\Donation;
use App\Models\Organization;
use App\Models\User;
use App\Support\Billing\CreatesDonationCheckout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeDonationCheckout;

// Flusso di avvio donazione (P4-6 STEP 2). Il seam di Checkout è un FAKE deterministico
// (nessuna rete). La riga nasce Pending: diventa Completed solo via webhook.

uses(RefreshDatabase::class);

beforeEach(function () {
    // Stripe "configurato" per i test del ramo positivo (chiavi fittizie, mai usate in
    // rete grazie al fake seam).
    config()->set('cashier.key', 'pk_test_fake');
    config()->set('cashier.secret', 'sk_test_fake');
});

/** Lega il fake seam e lo restituisce per le asserzioni. */
function bindFakeCheckout(): FakeDonationCheckout
{
    $fake = new FakeDonationCheckout;
    app()->instance(CreatesDonationCheckout::class, $fake);

    return $fake;
}

it('creates a pending donation for a logged-in donor and returns the checkout url', function () {
    $fake = bindFakeCheckout();
    $org = Organization::factory()->verified()->create();
    $donor = User::factory()->create(['name' => 'Mario Rossi', 'email' => 'mario@example.test']);

    $url = app(StartDonationCheckout::class)->handle(
        organization: $org,
        amountCents: 2_500,
        currency: 'eur',
        donor: $donor,
        message: 'Grazie del lavoro che fate',
    );

    expect($url)->toBe($fake->url);

    $donation = Donation::query()->sole();
    expect($donation->organization_id)->toBe($org->getKey())
        ->and($donation->donor_user_id)->toBe($donor->getKey())
        ->and($donation->donor_name)->toBe('Mario Rossi')
        ->and($donation->donor_email)->toBe('mario@example.test')
        ->and($donation->amount)->toBe(2_500)
        ->and($donation->currency)->toBe('eur')
        ->and($donation->type)->toBe(DonationType::OneOff)
        ->and($donation->status)->toBe(DonationStatus::Pending)
        ->and($donation->provider)->toBe('stripe')
        ->and($donation->provider_ref)->toBe($fake->id)
        ->and($donation->message)->toBe('Grazie del lavoro che fate')
        ->and($donation->paid_at)->toBeNull();

    // Il fake ha ricevuto la donazione da cui costruire la sessione.
    expect($fake->lastDonation?->is($donation))->toBeTrue();
});

it('creates a pending donation for a guest with name and email', function () {
    bindFakeCheckout();
    $org = Organization::factory()->verified()->create();

    app(StartDonationCheckout::class)->handle(
        organization: $org,
        amountCents: 1_000,
        currency: 'eur',
        donor: null,
        donorName: 'Ospite Generoso',
        donorEmail: 'ospite@example.test',
    );

    $donation = Donation::query()->sole();
    expect($donation->donor_user_id)->toBeNull()
        ->and($donation->donor_name)->toBe('Ospite Generoso')
        ->and($donation->donor_email)->toBe('ospite@example.test')
        ->and($donation->status)->toBe(DonationStatus::Pending);
});

it('throws when stripe is not configured and writes no row', function () {
    config()->set('cashier.key', null);
    config()->set('cashier.secret', null);
    bindFakeCheckout();
    $org = Organization::factory()->verified()->create();

    expect(fn () => app(StartDonationCheckout::class)->handle(
        organization: $org,
        amountCents: 2_500,
        currency: 'eur',
        donor: User::factory()->create(),
    ))->toThrow(DonationsNotConfigured::class);

    expect(Donation::query()->count())->toBe(0);
});

it('rejects an amount below the minimum', function () {
    bindFakeCheckout();
    $org = Organization::factory()->verified()->create();

    expect(fn () => app(StartDonationCheckout::class)->handle(
        organization: $org,
        amountCents: 50, // 0,50 € < 1 €
        currency: 'eur',
        donor: User::factory()->create(),
    ))->toThrow(InvalidDonationAmount::class);

    expect(Donation::query()->count())->toBe(0);
});

it('rejects an amount above the maximum', function () {
    bindFakeCheckout();
    $org = Organization::factory()->verified()->create();

    expect(fn () => app(StartDonationCheckout::class)->handle(
        organization: $org,
        amountCents: 2_000_000, // 20.000 € > 10.000 €
        currency: 'eur',
        donor: User::factory()->create(),
    ))->toThrow(InvalidDonationAmount::class);

    expect(Donation::query()->count())->toBe(0);
});

it('rejects a guest without an email', function () {
    bindFakeCheckout();
    $org = Organization::factory()->verified()->create();

    expect(fn () => app(StartDonationCheckout::class)->handle(
        organization: $org,
        amountCents: 2_500,
        currency: 'eur',
        donor: null,
        donorName: 'Senza Email',
        donorEmail: null,
    ))->toThrow(MissingDonorIdentity::class);

    expect(Donation::query()->count())->toBe(0);
});

it('passes a deterministic session id from the fake as provider_ref', function () {
    $fake = bindFakeCheckout();
    $fake->id = 'cs_test_deterministic_123';
    $fake->url = 'https://checkout.stripe.test/c/pay/cs_test_deterministic_123';

    $org = Organization::factory()->verified()->create();

    $url = app(StartDonationCheckout::class)->handle(
        organization: $org,
        amountCents: 5_000,
        currency: 'eur',
        donor: User::factory()->create(),
    );

    expect($url)->toBe('https://checkout.stripe.test/c/pay/cs_test_deterministic_123')
        ->and(Donation::query()->sole()->provider_ref)->toBe('cs_test_deterministic_123');
});
