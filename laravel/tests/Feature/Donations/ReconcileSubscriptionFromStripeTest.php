<?php

declare(strict_types=1);

use App\Actions\Donations\ReconcileSubscriptionFromStripe;
use App\Enums\DonationStatus;
use App\Enums\DonationType;
use App\Enums\RecurringDonationStatus;
use App\Models\Donation;
use App\Models\Organization;
use App\Models\RecurringDonation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

// Riconciliazione stato donazione RICORRENTE da eventi Stripe subscription/invoice (P4-7),
// testata direttamente sull'Action (senza HTTP): attivazione, ledger idempotente,
// transizioni e matching. Nessuna rete: costruiamo gli oggetti evento a mano.

uses(RefreshDatabase::class);

/** Ricorrente Pending (senza subscription id) per un ente verificato. */
function pendingRecurring(): RecurringDonation
{
    $org = Organization::factory()->verified()->create();
    $donor = User::factory()->create(['name' => 'Donatrice', 'email' => 'don@example.test']);

    return RecurringDonation::factory()->forOrganization($org)->forDonor($donor)->pending()->create();
}

it('activates the recurring donation and stores the subscription id on checkout.session.completed', function () {
    $recurring = pendingRecurring();

    $applied = app(ReconcileSubscriptionFromStripe::class)->handle('checkout.session.completed', [
        'id' => 'cs_sub_1',
        'mode' => 'subscription',
        'subscription' => 'sub_ABC',
        'metadata' => ['recurring_donation_id' => (string) $recurring->getKey()],
    ]);

    expect($applied)->toBeTrue();
    $recurring->refresh();
    expect($recurring->status)->toBe(RecurringDonationStatus::Active)
        ->and($recurring->stripe_subscription_id)->toBe('sub_ABC')
        ->and($recurring->started_at)->not->toBeNull();
});

it('is a no-op on a second activation of an already active recurring donation', function () {
    $recurring = pendingRecurring();
    $action = app(ReconcileSubscriptionFromStripe::class);

    $object = [
        'id' => 'cs_sub_2',
        'mode' => 'subscription',
        'subscription' => 'sub_DEF',
        'metadata' => ['recurring_donation_id' => (string) $recurring->getKey()],
    ];

    expect($action->handle('checkout.session.completed', $object))->toBeTrue()
        ->and($action->handle('checkout.session.completed', $object))->toBeFalse();
});

it('records a ledger donation row on invoice.paid matched by subscription id', function () {
    $recurring = pendingRecurring();
    $recurring->update([
        'status' => RecurringDonationStatus::Active,
        'stripe_subscription_id' => 'sub_LEDGER',
        'started_at' => now(),
    ]);

    $applied = app(ReconcileSubscriptionFromStripe::class)->handle('invoice.paid', [
        'id' => 'in_001',
        'subscription' => 'sub_LEDGER',
        'amount_paid' => 1_500,
        'currency' => 'eur',
    ]);

    expect($applied)->toBeTrue();

    $charge = Donation::query()->sole();
    expect($charge->recurring_donation_id)->toBe($recurring->getKey())
        ->and($charge->organization_id)->toBe($recurring->organization_id)
        ->and($charge->donor_user_id)->toBe($recurring->user_id)
        ->and($charge->donor_name)->toBe('Donatrice')
        ->and($charge->donor_email)->toBe('don@example.test')
        ->and($charge->amount)->toBe(1_500)
        ->and($charge->currency)->toBe('eur')
        ->and($charge->type)->toBe(DonationType::Recurring)
        ->and($charge->status)->toBe(DonationStatus::Completed)
        ->and($charge->provider)->toBe('stripe')
        ->and($charge->provider_ref)->toBe('in_001')
        ->and($charge->paid_at)->not->toBeNull();
});

it('is idempotent: a second invoice with the same id does not create a duplicate ledger row', function () {
    $recurring = pendingRecurring();
    $recurring->update([
        'status' => RecurringDonationStatus::Active,
        'stripe_subscription_id' => 'sub_DUP',
        'started_at' => now(),
    ]);

    $action = app(ReconcileSubscriptionFromStripe::class);
    $object = [
        'id' => 'in_dup',
        'subscription' => 'sub_DUP',
        'amount_paid' => 1_000,
        'currency' => 'eur',
    ];

    expect($action->handle('invoice.paid', $object))->toBeTrue()
        ->and($action->handle('invoice.paid', $object))->toBeFalse()
        ->and(Donation::query()->count())->toBe(1);
});

it('treats invoice.payment_succeeded as an alias of invoice.paid', function () {
    $recurring = pendingRecurring();
    $recurring->update([
        'status' => RecurringDonationStatus::Active,
        'stripe_subscription_id' => 'sub_ALIAS',
        'started_at' => now(),
    ]);

    app(ReconcileSubscriptionFromStripe::class)->handle('invoice.payment_succeeded', [
        'id' => 'in_alias',
        'subscription' => 'sub_ALIAS',
        'amount_paid' => 2_000,
        'currency' => 'eur',
    ]);

    expect(Donation::query()->where('provider_ref', 'in_alias')->count())->toBe(1);
});

it('recovers a past-due recurring donation back to active on a later invoice.paid', function () {
    $recurring = pendingRecurring();
    $recurring->update([
        'status' => RecurringDonationStatus::PastDue,
        'stripe_subscription_id' => 'sub_RECOVER',
        'started_at' => now()->subMonth(),
    ]);

    app(ReconcileSubscriptionFromStripe::class)->handle('invoice.paid', [
        'id' => 'in_recover',
        'subscription' => 'sub_RECOVER',
        'amount_paid' => 1_000,
        'currency' => 'eur',
    ]);

    expect($recurring->refresh()->status)->toBe(RecurringDonationStatus::Active)
        ->and(Donation::query()->where('provider_ref', 'in_recover')->count())->toBe(1);
});

it('marks the recurring donation past due on invoice.payment_failed', function () {
    $recurring = pendingRecurring();
    $recurring->update([
        'status' => RecurringDonationStatus::Active,
        'stripe_subscription_id' => 'sub_FAIL',
        'started_at' => now(),
    ]);

    $applied = app(ReconcileSubscriptionFromStripe::class)->handle('invoice.payment_failed', [
        'id' => 'in_fail',
        'subscription' => 'sub_FAIL',
    ]);

    expect($applied)->toBeTrue()
        ->and($recurring->refresh()->status)->toBe(RecurringDonationStatus::PastDue);
});

it('cancels the recurring donation on customer.subscription.deleted', function () {
    $recurring = pendingRecurring();
    $recurring->update([
        'status' => RecurringDonationStatus::Active,
        'stripe_subscription_id' => 'sub_DEL',
        'started_at' => now(),
    ]);

    $applied = app(ReconcileSubscriptionFromStripe::class)->handle('customer.subscription.deleted', [
        'id' => 'sub_DEL',
        'metadata' => ['recurring_donation_id' => (string) $recurring->getKey()],
    ]);

    expect($applied)->toBeTrue();
    $recurring->refresh();
    expect($recurring->status)->toBe(RecurringDonationStatus::Canceled)
        ->and($recurring->canceled_at)->not->toBeNull();
});

it('returns false (no-op) for an unrelated event type', function () {
    $recurring = pendingRecurring();

    $applied = app(ReconcileSubscriptionFromStripe::class)->handle('payment_intent.created', [
        'id' => 'pi_123',
    ]);

    expect($applied)->toBeFalse()
        ->and($recurring->refresh()->status)->toBe(RecurringDonationStatus::Pending);
});

it('returns false when no recurring donation matches the event', function () {
    $applied = app(ReconcileSubscriptionFromStripe::class)->handle('invoice.paid', [
        'id' => 'in_orphan',
        'subscription' => 'sub_never_seen',
        'amount_paid' => 1_000,
        'currency' => 'eur',
    ]);

    expect($applied)->toBeFalse()
        ->and(Donation::query()->count())->toBe(0);
});
