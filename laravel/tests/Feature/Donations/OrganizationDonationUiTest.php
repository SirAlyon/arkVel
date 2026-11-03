<?php

declare(strict_types=1);

use App\Enums\DonationStatus;
use App\Livewire\OrganizationPage;
use App\Models\Donation;
use App\Models\Organization;
use App\Models\User;
use App\Support\Billing\CreatesDonationCheckout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FakeDonationCheckout;

// Sezione pubblica di donazione (P4-6 STEP 4). Config-gated su StripeConfig::configured().
// Il submit delega a StartDonationCheckout col FAKE seam e redirige all'URL di Checkout.

uses(RefreshDatabase::class);

/** Stripe "configurato" via config (DB-over-env: qui usiamo l'.env/config). */
function configureStripe(): void
{
    config()->set('cashier.key', 'pk_test_fake');
    config()->set('cashier.secret', 'sk_test_fake');
}

/** Lega il fake seam e lo restituisce per le asserzioni. */
function fakeCheckout(): FakeDonationCheckout
{
    $fake = new FakeDonationCheckout;
    app()->instance(CreatesDonationCheckout::class, $fake);

    return $fake;
}

it('hides the donation section when stripe is not configured', function () {
    config()->set('cashier.key', null);
    config()->set('cashier.secret', null);

    $org = Organization::factory()->verified()->create();

    Livewire::test(OrganizationPage::class, ['organization' => $org])
        ->assertOk()
        ->assertDontSee(__('donations.public.heading', ['name' => $org->name]))
        ->assertSet('donationAmount', '');
});

it('shows the donation section when stripe is configured', function () {
    configureStripe();
    $org = Organization::factory()->verified()->create(['name' => 'Rifugio Buono']);

    Livewire::test(OrganizationPage::class, ['organization' => $org])
        ->assertOk()
        ->assertSee('Sostieni Rifugio Buono');
});

it('validates that the amount is required', function () {
    configureStripe();
    fakeCheckout();
    $org = Organization::factory()->verified()->create();

    Livewire::actingAs(User::factory()->create())
        ->test(OrganizationPage::class, ['organization' => $org])
        ->set('donationAmount', '')
        ->call('donate')
        ->assertHasErrors(['donationAmount' => 'required']);

    expect(Donation::query()->count())->toBe(0);
});

it('validates that the amount is within range', function () {
    configureStripe();
    fakeCheckout();
    $org = Organization::factory()->verified()->create();

    Livewire::actingAs(User::factory()->create())
        ->test(OrganizationPage::class, ['organization' => $org])
        ->set('donationAmount', '0.50') // < 1 €
        ->call('donate')
        ->assertHasErrors(['donationAmount' => 'min']);

    expect(Donation::query()->count())->toBe(0);
});

it('requires a guest email', function () {
    configureStripe();
    fakeCheckout();
    $org = Organization::factory()->verified()->create();

    // Nessun utente autenticato → è richiesta l'email.
    Livewire::test(OrganizationPage::class, ['organization' => $org])
        ->set('donationAmount', '25')
        ->set('donorEmail', '')
        ->call('donate')
        ->assertHasErrors(['donorEmail' => 'required']);

    expect(Donation::query()->count())->toBe(0);
});

it('lets a logged-in donor submit and redirects to the checkout url', function () {
    configureStripe();
    $fake = fakeCheckout();
    $org = Organization::factory()->verified()->create();
    $donor = User::factory()->create();

    Livewire::actingAs($donor)
        ->test(OrganizationPage::class, ['organization' => $org])
        ->set('donationAmount', '25')
        ->set('donationMessage', 'Bravi!')
        ->call('donate')
        ->assertHasNoErrors()
        ->assertRedirect($fake->url);

    $donation = Donation::query()->sole();
    expect($donation->donor_user_id)->toBe($donor->getKey())
        ->and($donation->amount)->toBe(2_500)
        ->and($donation->status)->toBe(DonationStatus::Pending)
        ->and($donation->provider_ref)->toBe($fake->id);
});

it('lets a guest donor submit with name and email and redirects to checkout', function () {
    configureStripe();
    $fake = fakeCheckout();
    $org = Organization::factory()->verified()->create();

    Livewire::test(OrganizationPage::class, ['organization' => $org])
        ->set('donationAmount', '10')
        ->set('donorName', 'Ospite')
        ->set('donorEmail', 'ospite@example.test')
        ->call('donate')
        ->assertHasNoErrors()
        ->assertRedirect($fake->url);

    $donation = Donation::query()->sole();
    expect($donation->donor_user_id)->toBeNull()
        ->and($donation->donor_name)->toBe('Ospite')
        ->and($donation->donor_email)->toBe('ospite@example.test')
        ->and($donation->amount)->toBe(1_000);
});

it('renders the thank-you landing for a verified organization', function () {
    $org = Organization::factory()->verified()->create(['name' => 'Rifugio Grazie']);

    $this->get(route('organizations.donate.thanks', $org))
        ->assertOk()
        ->assertSee(__('donations.public.thanks_title'))
        ->assertSee('Rifugio Grazie');
});

it('renders the cancel landing for a verified organization', function () {
    $org = Organization::factory()->verified()->create();

    $this->get(route('organizations.donate.cancel', $org))
        ->assertOk()
        ->assertSee(__('donations.public.cancel_title'));
});

it('returns 404 on the thank-you landing for a non-verified organization', function () {
    $org = Organization::factory()->pending()->create();

    $this->get(route('organizations.donate.thanks', $org))->assertNotFound();
});
