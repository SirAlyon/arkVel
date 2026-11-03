<?php

declare(strict_types=1);

use App\Enums\RecurringDonationStatus;
use App\Livewire\OrganizationPage;
use App\Models\Organization;
use App\Models\RecurringDonation;
use App\Models\User;
use App\Support\Billing\ManagesRecurringDonation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FakeRecurringDonationCheckout;

// Sezione pubblica di donazione MENSILE (P4-7). Config-gated. La mensile è riservata agli
// utenti autenticati; il submit delega a StartRecurringDonationCheckout col FAKE seam.

uses(RefreshDatabase::class);

function configureStripeForRecurring(): void
{
    config()->set('cashier.key', 'pk_test_fake');
    config()->set('cashier.secret', 'sk_test_fake');
}

function fakeRecurringCheckout(): FakeRecurringDonationCheckout
{
    $fake = new FakeRecurringDonationCheckout;
    app()->instance(ManagesRecurringDonation::class, $fake);

    return $fake;
}

it('shows the monthly option to authenticated users', function () {
    configureStripeForRecurring();
    $org = Organization::factory()->verified()->create();

    Livewire::actingAs(User::factory()->create())
        ->test(OrganizationPage::class, ['organization' => $org])
        ->assertOk()
        ->assertSet('donationFrequency', 'one_off')
        ->assertSee(__('donations.public.frequency_monthly'))
        ->assertDontSee(__('donations.public.monthly_requires_login'));
});

it('shows a login hint instead of the monthly option for guests', function () {
    configureStripeForRecurring();
    $org = Organization::factory()->verified()->create();

    Livewire::test(OrganizationPage::class, ['organization' => $org])
        ->assertOk()
        ->assertSee(__('donations.public.monthly_requires_login'));
});

it('lets an authenticated donor start a monthly donation and redirects to checkout', function () {
    configureStripeForRecurring();
    $fake = fakeRecurringCheckout();
    $org = Organization::factory()->verified()->create();
    $donor = User::factory()->create();

    Livewire::actingAs($donor)
        ->test(OrganizationPage::class, ['organization' => $org])
        ->set('donationFrequency', 'monthly')
        ->set('donationAmount', '10')
        ->call('donate')
        ->assertHasNoErrors()
        ->assertRedirect($fake->url);

    $recurring = RecurringDonation::query()->sole();
    expect($recurring->user_id)->toBe($donor->getKey())
        ->and($recurring->amount)->toBe(1_000)
        ->and($recurring->status)->toBe(RecurringDonationStatus::Pending);
});

it('refuses a monthly donation from a guest and creates no recurring row', function () {
    configureStripeForRecurring();
    fakeRecurringCheckout();
    $org = Organization::factory()->verified()->create();

    Livewire::test(OrganizationPage::class, ['organization' => $org])
        ->set('donationFrequency', 'monthly')
        ->set('donationAmount', '25')
        ->set('donorEmail', 'ospite@example.test')
        ->call('donate')
        ->assertNoRedirect()
        ->assertSet('donationError', __('donations.public.monthly_requires_login'));

    expect(RecurringDonation::query()->count())->toBe(0);
});
