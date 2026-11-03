<?php

declare(strict_types=1);

use App\Enums\DonationStatus;
use App\Enums\DonationType;
use App\Models\Donation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('casts amount to int, type/status to their enums and paid_at to Carbon', function () {
    $donation = Donation::factory()->recurring()->completed()->create([
        'amount' => 2500,
    ]);

    expect($donation->amount)->toBe(2500)
        ->and($donation->type)->toBe(DonationType::Recurring)
        ->and($donation->status)->toBe(DonationStatus::Completed)
        ->and($donation->paid_at)->toBeInstanceOf(Carbon::class);
});

it('persists the enum string values in the columns', function () {
    $donation = Donation::factory()->oneOff()->failed()->create();

    expect($donation->getRawOriginal('type'))->toBe(DonationType::OneOff->value)
        ->and($donation->getRawOriginal('status'))->toBe(DonationStatus::Failed->value);
});

it('defaults to a pending one-off stripe donation in euros via the factory', function () {
    $donation = Donation::factory()->create();

    expect($donation->status)->toBe(DonationStatus::Pending)
        ->and($donation->type)->toBe(DonationType::OneOff)
        ->and($donation->provider)->toBe('stripe')
        ->and($donation->currency)->toBe('eur')
        ->and($donation->paid_at)->toBeNull();
});

it('links to the beneficiary organization and the donor user', function () {
    $organization = Organization::factory()->create();
    $donor = User::factory()->create();

    $donation = Donation::factory()
        ->forOrganization($organization)
        ->forDonor($donor)
        ->create();

    expect($donation->organization)->toBeInstanceOf(Organization::class)
        ->and($donation->organization->is($organization))->toBeTrue()
        ->and($donation->donor)->toBeInstanceOf(User::class)
        ->and($donation->donor->is($donor))->toBeTrue();
});

it('allows anonymous donations with a null donor', function () {
    $donation = Donation::factory()->anonymous()->create();

    expect($donation->donor_user_id)->toBeNull()
        ->and($donation->donor)->toBeNull()
        ->and($donation->donor_name)->toBeNull()
        ->and($donation->donor_email)->toBeNull();
});

it('keeps the donation but nulls the donor when the user is deleted', function () {
    $donor = User::factory()->create();
    $donation = Donation::factory()->forDonor($donor)->create();

    $donor->delete();

    expect($donation->fresh()->donor_user_id)->toBeNull();
});

it('converts the cents amount to euros', function () {
    $donation = Donation::factory()->make(['amount' => 1550]);

    expect($donation->amountInEuros())->toBe(15.5);
});

it('scopes completed donations only', function () {
    Donation::factory()->completed()->create();
    Donation::factory()->pending()->create();
    Donation::factory()->failed()->create();
    Donation::factory()->refunded()->create();

    $completed = Donation::query()->completed()->get();

    expect($completed)->toHaveCount(1)
        ->and($completed->first()->status)->toBe(DonationStatus::Completed);
});

it('is reachable from the organization donations relation', function () {
    $organization = Organization::factory()->create();
    Donation::factory()->forOrganization($organization)->count(2)->create();
    Donation::factory()->create();

    expect($organization->donations()->count())->toBe(2);
});
