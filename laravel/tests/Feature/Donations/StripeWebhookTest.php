<?php

declare(strict_types=1);

use App\Enums\DonationStatus;
use App\Models\Donation;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

// Webhook Stripe (P4-6 STEP 3): riconciliazione idempotente dello stato donazione a
// partire dagli eventi Checkout. Nessuna rete: costruiamo i payload a mano. La firma è
// saltata quando il webhook secret è vuoto (default dei test), verificata quando è
// impostato (firma HMAC costruita in-test).

uses(RefreshDatabase::class);

beforeEach(function () {
    // Default: webhook secret vuoto → verifica firma SALTATA (dev/test).
    config()->set('cashier.webhook.secret', null);
});

/** Donazione pending con un provider_ref (id sessione) noto per il matching. */
function pendingDonation(string $sessionId = 'cs_test_abc'): Donation
{
    $org = Organization::factory()->verified()->create();

    return Donation::factory()
        ->forOrganization($org)
        ->pending()
        ->create(['provider_ref' => $sessionId]);
}

/**
 * Costruisce un payload di evento Checkout minimale (solo i campi che leggiamo).
 *
 * @return array<string, mixed>
 */
function checkoutEvent(string $type, string $sessionId, ?int $donationId = null): array
{
    return [
        'id' => 'evt_'.uniqid(),
        'type' => $type,
        'data' => [
            'object' => [
                'id' => $sessionId,
                'object' => 'checkout.session',
                'metadata' => $donationId !== null ? ['donation_id' => (string) $donationId] : [],
            ],
        ],
    ];
}

it('completes a pending donation on checkout.session.completed and sets paid_at', function () {
    $donation = pendingDonation('cs_test_complete');

    $response = $this->postJson('/stripe/webhook', checkoutEvent('checkout.session.completed', 'cs_test_complete'));

    $response->assertOk();

    $donation->refresh();
    expect($donation->status)->toBe(DonationStatus::Completed)
        ->and($donation->paid_at)->not->toBeNull();
});

it('matches the donation by metadata.donation_id when provider_ref differs', function () {
    $donation = pendingDonation('cs_test_known');

    // Il provider_ref dell'evento non combacia, ma metadata.donation_id sì.
    $response = $this->postJson(
        '/stripe/webhook',
        checkoutEvent('checkout.session.completed', 'cs_unknown_session', $donation->getKey()),
    );

    $response->assertOk();
    expect($donation->refresh()->status)->toBe(DonationStatus::Completed);
});

it('is idempotent: a second completed delivery is a no-op', function () {
    $donation = pendingDonation('cs_test_dup');

    $this->postJson('/stripe/webhook', checkoutEvent('checkout.session.completed', 'cs_test_dup'))->assertOk();
    $firstPaidAt = $donation->refresh()->paid_at;

    // Seconda consegna dello stesso esito: niente cambio di stato, niente eccezione.
    $this->postJson('/stripe/webhook', checkoutEvent('checkout.session.completed', 'cs_test_dup'))->assertOk();

    $donation->refresh();
    expect($donation->status)->toBe(DonationStatus::Completed)
        ->and($donation->paid_at?->equalTo($firstPaidAt))->toBeTrue();
});

it('completes on async_payment_succeeded (SEPA settles later)', function () {
    $donation = pendingDonation('cs_test_sepa');

    $this->postJson('/stripe/webhook', checkoutEvent('checkout.session.async_payment_succeeded', 'cs_test_sepa'))
        ->assertOk();

    expect($donation->refresh()->status)->toBe(DonationStatus::Completed);
});

it('fails a pending donation on checkout.session.expired', function () {
    $donation = pendingDonation('cs_test_expired');

    $this->postJson('/stripe/webhook', checkoutEvent('checkout.session.expired', 'cs_test_expired'))->assertOk();

    expect($donation->refresh()->status)->toBe(DonationStatus::Failed);
});

it('fails a pending donation on async_payment_failed', function () {
    $donation = pendingDonation('cs_test_failed');

    $this->postJson('/stripe/webhook', checkoutEvent('checkout.session.async_payment_failed', 'cs_test_failed'))
        ->assertOk();

    expect($donation->refresh()->status)->toBe(DonationStatus::Failed);
});

it('does not regress a completed donation to failed on a late expired event (idempotent graph)', function () {
    $org = Organization::factory()->verified()->create();
    $donation = Donation::factory()->forOrganization($org)->completed()->create(['provider_ref' => 'cs_test_late']);

    $this->postJson('/stripe/webhook', checkoutEvent('checkout.session.expired', 'cs_test_late'))->assertOk();

    expect($donation->refresh()->status)->toBe(DonationStatus::Completed);
});

it('returns 200 no-op for an unknown event type', function () {
    $donation = pendingDonation('cs_test_unknown');

    $this->postJson('/stripe/webhook', checkoutEvent('payment_intent.created', 'cs_test_unknown'))->assertOk();

    expect($donation->refresh()->status)->toBe(DonationStatus::Pending);
});

it('returns 200 no-op when no donation matches', function () {
    $this->postJson('/stripe/webhook', checkoutEvent('checkout.session.completed', 'cs_never_seen'))->assertOk();
});

it('rejects a bad signature with 400 when the webhook secret is set', function () {
    config()->set('cashier.webhook.secret', 'whsec_test_secret');
    $donation = pendingDonation('cs_test_sig');

    $payload = json_encode(checkoutEvent('checkout.session.completed', 'cs_test_sig'), JSON_THROW_ON_ERROR);

    $response = $this->call(
        'POST',
        '/stripe/webhook',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => 't=123,v1=deadbeef'],
        $payload,
    );

    $response->assertStatus(400);
    expect($donation->refresh()->status)->toBe(DonationStatus::Pending);
});

it('processes a valid signed payload when the webhook secret is set', function () {
    $secret = 'whsec_test_secret';
    config()->set('cashier.webhook.secret', $secret);
    $donation = pendingDonation('cs_test_signed');

    $payload = json_encode(checkoutEvent('checkout.session.completed', 'cs_test_signed'), JSON_THROW_ON_ERROR);

    // Firma secondo lo schema Stripe: t=<timestamp>,v1=HMAC-SHA256(<t>.<payload>).
    $timestamp = time();
    $signature = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
    $header = sprintf('t=%d,v1=%s', $timestamp, $signature);

    $response = $this->call(
        'POST',
        '/stripe/webhook',
        [],
        [],
        [],
        ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $header],
        $payload,
    );

    $response->assertOk();
    expect($donation->refresh()->status)->toBe(DonationStatus::Completed);
});
