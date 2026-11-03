<?php

declare(strict_types=1);

use App\Actions\Donations\ReconcileDonationFromStripe;
use App\Enums\DonationStatus;
use App\Models\Donation;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

// Riconciliazione stato donazione da eventi Stripe (P4-6 STEP 3), testata direttamente
// sull'Action (senza HTTP): mappa evento→stato, idempotenza e matching.

uses(RefreshDatabase::class);

function pendingDonationFor(string $sessionId = 'cs_rec'): Donation
{
    $org = Organization::factory()->verified()->create();

    return Donation::factory()->forOrganization($org)->pending()->create(['provider_ref' => $sessionId]);
}

it('completes and sets paid_at by session id', function () {
    $donation = pendingDonationFor('cs_rec_1');

    $applied = app(ReconcileDonationFromStripe::class)
        ->handle('checkout.session.completed', 'cs_rec_1', null);

    expect($applied)->toBeTrue();
    $donation->refresh();
    expect($donation->status)->toBe(DonationStatus::Completed)
        ->and($donation->paid_at)->not->toBeNull();
});

it('matches by donation id when session id is unknown', function () {
    $donation = pendingDonationFor('cs_rec_2');

    $applied = app(ReconcileDonationFromStripe::class)
        ->handle('checkout.session.completed', 'cs_other', $donation->getKey());

    expect($applied)->toBeTrue()
        ->and($donation->refresh()->status)->toBe(DonationStatus::Completed);
});

it('returns false (no-op) for an unknown event type', function () {
    $donation = pendingDonationFor('cs_rec_3');

    $applied = app(ReconcileDonationFromStripe::class)
        ->handle('invoice.paid', 'cs_rec_3', null);

    expect($applied)->toBeFalse()
        ->and($donation->refresh()->status)->toBe(DonationStatus::Pending);
});

it('returns false when no donation matches', function () {
    $applied = app(ReconcileDonationFromStripe::class)
        ->handle('checkout.session.completed', 'cs_missing', null);

    expect($applied)->toBeFalse();
});

it('is idempotent on a second completed event', function () {
    $donation = pendingDonationFor('cs_rec_4');
    $action = app(ReconcileDonationFromStripe::class);

    expect($action->handle('checkout.session.completed', 'cs_rec_4', null))->toBeTrue()
        ->and($action->handle('checkout.session.completed', 'cs_rec_4', null))->toBeFalse();
});

it('fails a pending donation on expired', function () {
    $donation = pendingDonationFor('cs_rec_5');

    app(ReconcileDonationFromStripe::class)->handle('checkout.session.expired', 'cs_rec_5', null);

    expect($donation->refresh()->status)->toBe(DonationStatus::Failed);
});

it('returns false with neither session id nor donation id', function () {
    $applied = app(ReconcileDonationFromStripe::class)
        ->handle('checkout.session.completed', null, null);

    expect($applied)->toBeFalse();
});
