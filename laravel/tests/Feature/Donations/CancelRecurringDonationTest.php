<?php

declare(strict_types=1);

use App\Actions\Donations\CancelRecurringDonation;
use App\Enums\RecurringDonationStatus;
use App\Models\Organization;
use App\Models\RecurringDonation;
use App\Models\User;
use App\Support\Billing\ManagesRecurringDonation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeRecurringDonationCheckout;

// Disdetta di una donazione ricorrente (P4-7). Il seam è un FAKE (nessuna rete):
// asseriamo la delega a Stripe, la transizione a Canceled e l'idempotenza.

uses(RefreshDatabase::class);

function bindFakeRecurringManager(): FakeRecurringDonationCheckout
{
    $fake = new FakeRecurringDonationCheckout;
    app()->instance(ManagesRecurringDonation::class, $fake);

    return $fake;
}

it('cancels an active recurring donation: calls stripe and transitions to canceled', function () {
    $fake = bindFakeRecurringManager();
    $donor = User::factory()->create();
    $recurring = RecurringDonation::factory()
        ->forDonor($donor)
        ->active()
        ->withSubscriptionId('sub_CANCEL')
        ->create();

    $applied = app(CancelRecurringDonation::class)->handle($recurring, $donor);

    expect($applied)->toBeTrue()
        ->and($fake->canceledSubscriptionId)->toBe('sub_CANCEL')
        ->and($fake->cancelCalls)->toBe(1);

    $recurring->refresh();
    expect($recurring->status)->toBe(RecurringDonationStatus::Canceled)
        ->and($recurring->canceled_at)->not->toBeNull();
});

it('cancels a pending recurring donation without calling stripe (no subscription yet)', function () {
    $fake = bindFakeRecurringManager();
    $donor = User::factory()->create();
    $recurring = RecurringDonation::factory()->forDonor($donor)->pending()->create();

    $applied = app(CancelRecurringDonation::class)->handle($recurring, $donor);

    expect($applied)->toBeTrue()
        ->and($fake->cancelCalls)->toBe(0)
        ->and($recurring->refresh()->status)->toBe(RecurringDonationStatus::Canceled);
});

it('is a no-op when the recurring donation is already canceled', function () {
    $fake = bindFakeRecurringManager();
    $donor = User::factory()->create();
    $recurring = RecurringDonation::factory()->forDonor($donor)->canceled()->create();
    $canceledAt = $recurring->canceled_at;

    $applied = app(CancelRecurringDonation::class)->handle($recurring, $donor);

    expect($applied)->toBeFalse()
        ->and($fake->cancelCalls)->toBe(0)
        ->and($recurring->refresh()->status)->toBe(RecurringDonationStatus::Canceled)
        ->and($recurring->canceled_at?->equalTo($canceledAt))->toBeTrue();
});

it('the policy forbids a non-owner from canceling', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $org = Organization::factory()->verified()->create();
    $recurring = RecurringDonation::factory()->forDonor($owner)->forOrganization($org)->active()->create();

    expect($owner->can('cancel', $recurring))->toBeTrue()
        ->and($other->can('cancel', $recurring))->toBeFalse()
        ->and($owner->can('view', $recurring))->toBeTrue()
        ->and($other->can('view', $recurring))->toBeFalse();
});
