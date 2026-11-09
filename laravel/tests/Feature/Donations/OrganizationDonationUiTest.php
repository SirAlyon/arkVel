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

/*
 | Widget di donazione ridisegnato (UI-6d): stessi campi, stesse regole, stessi parametri
 | passati a StartDonationCheckout; markup a componenti x-ui (tag pressed, radio, field).
 */

it('renders the widget with presets, free amount and guest fields', function () {
    configureStripe();
    $org = Organization::factory()->verified()->create();

    Livewire::test(OrganizationPage::class, ['organization' => $org])
        ->assertSeeHtml('data-donation-widget')
        ->assertSeeHtml('wire:submit="donate"')
        ->assertSeeHtml('data-donation-preset="5"')
        ->assertSeeHtml('data-donation-preset="50"')
        ->assertSeeHtml('aria-pressed="false"')
        ->assertSee("25\u{00A0}€") // Number::currency: spazio non separabile
        ->assertSeeHtml('type="number"')
        ->assertSeeHtml('min="1"')
        ->assertSeeHtml('max="10000"')
        ->assertSeeHtml('wire:model="donationAmount"')
        ->assertSeeHtml('wire:model="donorName"')
        ->assertSeeHtml('wire:model="donorEmail"')
        ->assertSeeHtml('wire:model="donationMessage"')
        ->assertSeeHtml('value="monthly"')
        ->assertSeeHtml('disabled')
        ->assertSeeHtml('href="'.route('login').'"');
});

it('marks the chosen preset as pressed', function () {
    configureStripe();
    $org = Organization::factory()->verified()->create();

    Livewire::test(OrganizationPage::class, ['organization' => $org])
        ->set('donationAmount', '25')
        ->assertSeeHtmlInOrder(['aria-pressed="true"', 'data-donation-preset="25"']);
});

it('hides the guest fields and enables the monthly option for signed-in donors', function () {
    configureStripe();
    $org = Organization::factory()->verified()->create();

    Livewire::actingAs(User::factory()->create())
        ->test(OrganizationPage::class, ['organization' => $org])
        ->assertDontSeeHtml('wire:model="donorEmail"')
        ->assertDontSeeHtml('data-donation-monthly-login')
        ->assertSee(__('page_organizations.donation.monthly_note'));
});

it('shows the unchanged validation messages in the error summary and under the fields', function () {
    configureStripe();
    fakeCheckout();
    $org = Organization::factory()->verified()->create();

    Livewire::test(OrganizationPage::class, ['organization' => $org])
        ->set('donationAmount', '20000')
        ->set('donorEmail', 'non-una-email')
        ->call('donate')
        ->assertHasErrors(['donationAmount' => 'max', 'donorEmail' => 'email'])
        ->assertSeeHtml('data-ui-error-summary')
        ->assertSee(__('donations.public.validation.amount_max'))
        ->assertSee(__('donations.public.validation.email_invalid'))
        ->assertSeeHtml('aria-invalid="true"');

    expect(Donation::query()->count())->toBe(0);
});

it('passes the same parameters to the checkout action as before', function () {
    configureStripe();
    $fake = fakeCheckout();
    $org = Organization::factory()->verified()->create();
    $donor = User::factory()->create(['name' => 'Giulia', 'email' => 'giulia@example.test']);

    // Un utente autenticato porta nome ed email dall'account: i campi ospite sono ignorati.
    Livewire::actingAs($donor)
        ->test(OrganizationPage::class, ['organization' => $org])
        ->set('donationAmount', '12.5')
        ->set('donorName', 'Altro nome')
        ->set('donorEmail', 'altra@example.test')
        ->set('donationMessage', '')
        ->call('donate')
        ->assertHasNoErrors()
        ->assertRedirect($fake->url);

    $donation = Donation::query()->sole();
    expect($donation->amount)->toBe(1_250)
        ->and($donation->currency)->toBe('eur')
        ->and($donation->organization_id)->toBe($org->getKey())
        ->and($donation->donor_name)->toBe('Giulia')
        ->and($donation->donor_email)->toBe('giulia@example.test')
        ->and($donation->message)->toBeNull()
        ->and($fake->lastSuccessUrl)->toBe(route('organizations.donate.thanks', $org))
        ->and($fake->lastCancelUrl)->toBe(route('organizations.donate.cancel', $org));
});

it('shows a form error when stripe stops being configured before the submit', function () {
    configureStripe();
    fakeCheckout();
    $org = Organization::factory()->verified()->create();

    $component = Livewire::actingAs(User::factory()->create())
        ->test(OrganizationPage::class, ['organization' => $org])
        ->set('donationAmount', '10');

    config()->set('cashier.key', null);
    config()->set('cashier.secret', null);

    $component->call('donate')
        ->assertNoRedirect()
        ->assertSet('donationError', __('donations.public.not_configured'));

    expect(Donation::query()->count())->toBe(0);
});
