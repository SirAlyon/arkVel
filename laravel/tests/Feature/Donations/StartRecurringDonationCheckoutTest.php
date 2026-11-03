<?php

declare(strict_types=1);

use App\Actions\Donations\StartRecurringDonationCheckout;
use App\Enums\RecurringDonationStatus;
use App\Exceptions\DonationsNotConfigured;
use App\Exceptions\InvalidDonationAmount;
use App\Exceptions\MissingDonorIdentity;
use App\Models\Organization;
use App\Models\RecurringDonation;
use App\Models\User;
use App\Support\Billing\ManagesRecurringDonation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeRecurringDonationCheckout;

// Avvio donazione RICORRENTE (P4-7). Il seam è un FAKE deterministico (nessuna rete). La
// riga nasce Pending con stripe_subscription_id null: diventa Active solo via webhook.

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('cashier.key', 'pk_test_fake');
    config()->set('cashier.secret', 'sk_test_fake');
});

/** Lega il fake seam ricorrente e lo restituisce per le asserzioni. */
function bindFakeRecurringCheckout(): FakeRecurringDonationCheckout
{
    $fake = new FakeRecurringDonationCheckout;
    app()->instance(ManagesRecurringDonation::class, $fake);

    return $fake;
}

it('creates a pending recurring donation for a logged-in donor and returns the checkout url', function () {
    $fake = bindFakeRecurringCheckout();
    $org = Organization::factory()->verified()->create();
    $donor = User::factory()->create(['name' => 'Mario Rossi', 'email' => 'mario@example.test']);

    $url = app(StartRecurringDonationCheckout::class)->handle(
        organization: $org,
        amountCents: 1_000,
        currency: 'eur',
        donor: $donor,
    );

    expect($url)->toBe($fake->url);

    $recurring = RecurringDonation::query()->sole();
    expect($recurring->user_id)->toBe($donor->getKey())
        ->and($recurring->organization_id)->toBe($org->getKey())
        ->and($recurring->amount)->toBe(1_000)
        ->and($recurring->currency)->toBe('eur')
        ->and($recurring->interval)->toBe('monthly')
        ->and($recurring->status)->toBe(RecurringDonationStatus::Pending)
        ->and($recurring->stripe_subscription_id)->toBeNull()
        ->and($recurring->started_at)->toBeNull();

    // Il fake ha ricevuto la ricorrente da cui costruire la sessione, con le relazioni
    // precaricate (ente + donatore) per nome prodotto ed email.
    expect($fake->lastRecurring?->is($recurring))->toBeTrue()
        ->and($fake->lastRecurring?->organization->is($org))->toBeTrue()
        ->and($fake->lastRecurring?->donor->is($donor))->toBeTrue();
});

it('throws when stripe is not configured and writes no row', function () {
    config()->set('cashier.key', null);
    config()->set('cashier.secret', null);
    bindFakeRecurringCheckout();
    $org = Organization::factory()->verified()->create();

    expect(fn () => app(StartRecurringDonationCheckout::class)->handle(
        organization: $org,
        amountCents: 1_000,
        currency: 'eur',
        donor: User::factory()->create(),
    ))->toThrow(DonationsNotConfigured::class);

    expect(RecurringDonation::query()->count())->toBe(0);
});

it('rejects an amount below the minimum', function () {
    bindFakeRecurringCheckout();
    $org = Organization::factory()->verified()->create();

    expect(fn () => app(StartRecurringDonationCheckout::class)->handle(
        organization: $org,
        amountCents: 50,
        currency: 'eur',
        donor: User::factory()->create(),
    ))->toThrow(InvalidDonationAmount::class);

    expect(RecurringDonation::query()->count())->toBe(0);
});

it('rejects an amount above the maximum', function () {
    bindFakeRecurringCheckout();
    $org = Organization::factory()->verified()->create();

    expect(fn () => app(StartRecurringDonationCheckout::class)->handle(
        organization: $org,
        amountCents: 2_000_000,
        currency: 'eur',
        donor: User::factory()->create(),
    ))->toThrow(InvalidDonationAmount::class);

    expect(RecurringDonation::query()->count())->toBe(0);
});

it('rejects a guest: recurring donations require an authenticated account', function () {
    bindFakeRecurringCheckout();
    $org = Organization::factory()->verified()->create();

    expect(fn () => app(StartRecurringDonationCheckout::class)->handle(
        organization: $org,
        amountCents: 1_000,
        currency: 'eur',
        donor: null,
    ))->toThrow(MissingDonorIdentity::class);

    expect(RecurringDonation::query()->count())->toBe(0);
});
