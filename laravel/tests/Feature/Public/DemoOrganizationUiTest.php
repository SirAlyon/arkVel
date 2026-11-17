<?php

declare(strict_types=1);

use App\Livewire\OrganizationPage;
use App\Models\Animal;
use App\Models\Donation;
use App\Models\Organization;
use App\Support\Billing\CreatesDonationCheckout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\FakeDonationCheckout;

// Sito pubblico per un ente dimostrativo: badge «Esempio» (pagina ente, card, scheda),
// avviso al posto delle CTA della scheda e del widget di donazione. Visibilità invariata.
// Per un ente normale nulla cambia (regressione).

uses(RefreshDatabase::class);

beforeEach(function () {
    app()->setLocale('it');
    // Stripe configurato: il widget comparirebbe, così si vede che è l'is_demo a toglierlo.
    config()->set('cashier.key', 'pk_test_fake');
    config()->set('cashier.secret', 'sk_test_fake');
});

/**
 * @return array{organization: Organization, animal: Animal}
 */
function demoUiOrganization(bool $demo): array
{
    $factory = Organization::factory()->verified();
    $organization = ($demo ? $factory->demo() : $factory)->create(['name' => 'Kiba']);
    $animal = Animal::factory()->available()->forOrganization($organization)->create(['name' => 'Argo']);

    return ['organization' => $organization, 'animal' => $animal];
}

it('shows the demo badge and notice instead of the action panel on the animal page', function () {
    ['animal' => $animal] = demoUiOrganization(demo: true);

    $this->get(route('animals.show', $animal))
        ->assertOk()
        ->assertSee('data-demo-badge', false)
        ->assertSee(__('demo.badge'))
        ->assertSee('data-demo-notice', false)
        ->assertSee(__('demo.notice_body'))
        ->assertDontSee('data-animal-open="booking"', false)
        ->assertDontSee('data-animal-open="contact"', false)
        ->assertDontSee('animal-detail.adoption-application-form', false)
        ->assertDontSee('animal-detail.foster-request-form', false)
        ->assertDontSee('animal-detail.appointment-booking', false)
        ->assertDontSee('animal-detail.contact-organization-form', false);
});

it('keeps the full action panel and no demo badge for a normal organization', function () {
    ['animal' => $animal] = demoUiOrganization(demo: false);

    $this->get(route('animals.show', $animal))
        ->assertOk()
        ->assertDontSee('data-demo-badge', false)
        ->assertDontSee('data-demo-notice', false)
        ->assertSee('data-animal-open="booking"', false)
        ->assertSee('data-animal-open="contact"', false)
        ->assertSee('animal-detail.adoption-application-form', false)
        ->assertSee('animal-detail.contact-organization-form', false);
});

it('marks the organization page and its animal cards and hides the donation widget', function () {
    ['organization' => $organization] = demoUiOrganization(demo: true);

    Livewire::test(OrganizationPage::class, ['organization' => $organization])
        ->assertOk()
        ->assertSee('data-demo-badge', false)
        ->assertSee('data-animal-card-demo', false)
        ->assertSee('data-demo-notice', false)
        ->assertDontSee('data-donation-widget', false)
        ->assertDontSee(__('donations.public.heading', ['name' => 'Kiba']));
});

it('keeps the donation widget and no demo marks for a normal organization', function () {
    ['organization' => $organization] = demoUiOrganization(demo: false);

    Livewire::test(OrganizationPage::class, ['organization' => $organization])
        ->assertOk()
        ->assertSee('data-donation-widget', false)
        ->assertDontSee('data-demo-badge', false)
        ->assertDontSee('data-animal-card-demo', false)
        ->assertDontSee('data-demo-notice', false);
});

it('refuses a crafted donation on the demo organization page with the translated message', function () {
    $fake = new FakeDonationCheckout;
    app()->instance(CreatesDonationCheckout::class, $fake);
    ['organization' => $organization] = demoUiOrganization(demo: true);

    Livewire::test(OrganizationPage::class, ['organization' => $organization])
        ->set('donationAmount', '10')
        ->set('donorEmail', 'guest@example.test')
        ->call('donate')
        ->assertNoRedirect()
        ->assertSet('donationError', __('demo.refused'));

    expect(Donation::query()->count())->toBe(0)
        ->and($fake->lastDonation)->toBeNull();
});

it('keeps a verified demo organization and its animals publicly visible', function () {
    ['organization' => $organization, 'animal' => $animal] = demoUiOrganization(demo: true);

    $this->get(route('organizations.show', $organization))->assertOk();
    $this->get(route('animals.show', $animal))->assertOk();
    expect(Animal::query()->publiclyVisible()->whereKey($animal->getKey())->exists())->toBeTrue()
        ->and(Organization::query()->verified()->whereKey($organization->getKey())->exists())->toBeTrue();
});

it('renders the demo badge on the generic animal card only when asked', function () {
    $demo = $this->blade('<x-ui.animal-card name="Argo" href="/a" :demo="true" />');
    $normal = $this->blade('<x-ui.animal-card name="Argo" href="/a" />');

    $demo->assertSee('data-animal-card-demo', false)->assertSee(__('demo.badge_hint'));
    $normal->assertDontSee('data-animal-card-demo', false);
});
