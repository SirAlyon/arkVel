<?php

declare(strict_types=1);

use App\Enums\DonationStatus;
use App\Enums\DonationType;
use App\Enums\RecurringDonationStatus;
use App\Models\Donation;
use App\Models\Organization;
use App\Models\RecurringDonation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

// Webhook Stripe per le ricorrenti (P4-7): instradamento subscription vs una tantum per
// `mode`, senza collisioni, e riconciliazione idempotente. Nessuna rete: payload a mano.
// Firma saltata quando il webhook secret è vuoto (default dei test).

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('cashier.webhook.secret', null);
});

/** Ricorrente Pending con subscription id noto per il matching. */
function pendingRecurringForWebhook(?string $subscriptionId = null): RecurringDonation
{
    $org = Organization::factory()->verified()->create();
    $donor = User::factory()->create();

    $factory = RecurringDonation::factory()->forOrganization($org)->forDonor($donor)->pending();

    if ($subscriptionId !== null) {
        $factory = $factory->withSubscriptionId($subscriptionId);
    }

    return $factory->create();
}

/**
 * @param  array<string, mixed>  $object
 * @return array<string, mixed>
 */
function stripeEvent(string $type, array $object): array
{
    return [
        'id' => 'evt_'.uniqid(),
        'type' => $type,
        'data' => ['object' => $object],
    ];
}

it('routes a subscription checkout session to the recurring reconciliation and activates it', function () {
    $recurring = pendingRecurringForWebhook();

    $this->postJson('/stripe/webhook', stripeEvent('checkout.session.completed', [
        'id' => 'cs_sub_webhook',
        'mode' => 'subscription',
        'subscription' => 'sub_WEBHOOK',
        'metadata' => ['recurring_donation_id' => (string) $recurring->getKey()],
    ]))->assertOk();

    $recurring->refresh();
    expect($recurring->status)->toBe(RecurringDonationStatus::Active)
        ->and($recurring->stripe_subscription_id)->toBe('sub_WEBHOOK');
});

it('does not touch recurring donations when a one-off checkout session completes', function () {
    // Una donazione una tantum pending + una ricorrente pending: l'evento one-off (mode
    // payment) non deve attivare la ricorrente.
    $org = Organization::factory()->verified()->create();
    $oneOff = Donation::factory()->forOrganization($org)->oneOff()->pending()->create(['provider_ref' => 'cs_oneoff']);
    $recurring = pendingRecurringForWebhook();

    $this->postJson('/stripe/webhook', stripeEvent('checkout.session.completed', [
        'id' => 'cs_oneoff',
        'mode' => 'payment',
        'metadata' => [],
    ]))->assertOk();

    expect($oneOff->refresh()->status)->toBe(DonationStatus::Completed)
        ->and($recurring->refresh()->status)->toBe(RecurringDonationStatus::Pending);
});

it('routes invoice.paid to the recurring reconciliation and records a ledger row', function () {
    $recurring = pendingRecurringForWebhook('sub_INV');
    $recurring->update(['status' => RecurringDonationStatus::Active, 'started_at' => now()]);

    $this->postJson('/stripe/webhook', stripeEvent('invoice.paid', [
        'id' => 'in_webhook',
        'subscription' => 'sub_INV',
        'amount_paid' => 1_200,
        'currency' => 'eur',
    ]))->assertOk();

    $charge = Donation::query()->where('provider_ref', 'in_webhook')->sole();
    expect($charge->type)->toBe(DonationType::Recurring)
        ->and($charge->status)->toBe(DonationStatus::Completed)
        ->and($charge->amount)->toBe(1_200)
        ->and($charge->recurring_donation_id)->toBe($recurring->getKey());
});

it('is idempotent on a duplicate invoice.paid delivery', function () {
    $recurring = pendingRecurringForWebhook('sub_IDEM');
    $recurring->update(['status' => RecurringDonationStatus::Active, 'started_at' => now()]);

    $payload = stripeEvent('invoice.paid', [
        'id' => 'in_idem',
        'subscription' => 'sub_IDEM',
        'amount_paid' => 1_000,
        'currency' => 'eur',
    ]);

    $this->postJson('/stripe/webhook', $payload)->assertOk();
    $this->postJson('/stripe/webhook', $payload)->assertOk();

    expect(Donation::query()->where('provider_ref', 'in_idem')->count())->toBe(1);
});

it('routes customer.subscription.deleted to the recurring reconciliation and cancels it', function () {
    $recurring = pendingRecurringForWebhook('sub_GONE');
    $recurring->update(['status' => RecurringDonationStatus::Active, 'started_at' => now()]);

    $this->postJson('/stripe/webhook', stripeEvent('customer.subscription.deleted', [
        'id' => 'sub_GONE',
        'metadata' => ['recurring_donation_id' => (string) $recurring->getKey()],
    ]))->assertOk();

    expect($recurring->refresh()->status)->toBe(RecurringDonationStatus::Canceled);
});

it('rejects a bad signature with 400 for a subscription event when the webhook secret is set', function () {
    config()->set('cashier.webhook.secret', 'whsec_test_secret');
    $recurring = pendingRecurringForWebhook();

    $payload = json_encode(stripeEvent('checkout.session.completed', [
        'id' => 'cs_sig',
        'mode' => 'subscription',
        'subscription' => 'sub_SIG',
        'metadata' => ['recurring_donation_id' => (string) $recurring->getKey()],
    ]), JSON_THROW_ON_ERROR);

    $this->call(
        'POST',
        '/stripe/webhook',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => 't=123,v1=deadbeef'],
        $payload,
    )->assertStatus(400);

    expect($recurring->refresh()->status)->toBe(RecurringDonationStatus::Pending);
});
