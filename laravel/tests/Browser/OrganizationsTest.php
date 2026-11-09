<?php

declare(strict_types=1);

use App\Enums\OrganizationType;
use App\Models\Animal;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Ui\BrowserScripts;

uses(RefreshDatabase::class);

/*
 | Pagine pubbliche degli enti (UI-6d) nel browser: indice con filtri, pagina ente con il
 | widget di donazione (tastiera, errori), pagine di ritorno dal Checkout. Accessibilità
 | (axe) in chiaro e in scuro, target ≥ 44 px, nessun overflow a 320 px.
 | Gira SOLO nel servizio `browser-tests`: `docker compose --profile testing run --rm browser-tests`.
 */

beforeEach(function (): void {
    // Stripe "configurato" (come nei feature test): il widget di donazione compare.
    config()->set('cashier.key', 'pk_test_fake');
    config()->set('cashier.secret', 'sk_test_fake');
});

/** Ripete la condizione JS (ogni 25 ms, al massimo $timeout ms) e restituisce l'ultimo esito. */
function organizationsEventually(string $condition, int $timeout = 5000): string
{
    return 'function () { return new Promise((resolve) => { const end = Date.now() + '.$timeout.'; const check = () => { let ok = false; try { ok = Boolean('.$condition.'); } catch (error) { ok = false; } if (ok || Date.now() > end) { resolve(ok); } else { setTimeout(check, 25); } }; check(); }); }';
}

/** Un ente con nome lungo, contatti, descrizione e qualche animale (anche adottato). */
function showcaseOrganization(): Organization
{
    $organization = Organization::factory()->verified()->create([
        'name' => 'Associazione Amici degli Animali della Valle del Serchio',
        'type' => OrganizationType::Associazione,
        'city' => 'Castelnuovo di Garfagnana',
        'region' => 'Toscana',
        'email' => 'segreteria.adozioni@amicidellanimalivalledelserchio.example',
        'phone' => '+39 0583 123456',
        'website' => 'https://amicidellanimali.example',
        'description' => ['it' => 'Da vent\'anni ci prendiamo cura dei cani e dei gatti della valle.', 'en' => 'For twenty years we have looked after the valley\'s dogs and cats.'],
    ]);
    Animal::factory()->available()->count(3)->forOrganization($organization)->create();
    Animal::factory()->adopted()->forOrganization($organization)->create();

    return $organization;
}

function directoryOrganizations(): void
{
    Organization::factory()->verified()->create(['name' => 'Rifugio Il Faro', 'region' => 'Liguria', 'type' => OrganizationType::Rifugio]);
    Organization::factory()->verified()->create(['name' => 'Canile Comunale di Bari', 'region' => 'Puglia', 'type' => OrganizationType::CanileComunale]);
    Organization::factory()->verified()->create(['name' => 'Oasi Felina di Torino', 'region' => 'Piemonte', 'type' => OrganizationType::Associazione]);
}

it('has no accessibility issues on the organization pages, in light and dark mode', function (string $page): void {
    directoryOrganizations();
    $organization = showcaseOrganization();

    $url = match ($page) {
        'index' => route('organizations.index'),
        'show' => route('organizations.show', $organization),
        'thanks' => route('organizations.donate.thanks', $organization),
        'cancelled' => route('organizations.donate.cancel', $organization),
    };

    visit($url)->withLocale('it-IT')
        ->assertNoJavaScriptErrors()
        ->assertScript('document.documentElement.dataset.themeForced ?? null', null)
        ->assertNoAccessibilityIssues();

    visit($url)->inDarkMode()->withLocale('it-IT')
        ->assertScript('document.documentElement.classList.contains("dark")', true)
        ->assertNoAccessibilityIssues();
})->with(['index', 'show', 'thanks', 'cancelled']);

it('has no overflow at 320 px and keeps the controls at least 44 px', function (string $page, string $scope): void {
    directoryOrganizations();
    $organization = showcaseOrganization();

    $url = match ($page) {
        'index' => route('organizations.index'),
        'show' => route('organizations.show', $organization),
        'thanks' => route('organizations.donate.thanks', $organization),
        'cancelled' => route('organizations.donate.cancel', $organization),
    };

    visit($url)->withLocale('it-IT')->resize(320, 800)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertScript(BrowserScripts::undersizedTargets($scope), []);
})->with([
    'index' => ['index', '[data-organization-index]'],
    'show' => ['show', '[data-organization-page]'],
    'thanks' => ['thanks', '[data-donation-landing]'],
    'cancelled' => ['cancelled', '[data-donation-landing]'],
]);

it('filters the organizations by type and region, keeping the filters in the address', function (): void {
    directoryOrganizations();

    $page = visit(route('organizations.index'))->withLocale('it-IT');

    $page->assertNoJavaScriptErrors()
        ->assertSee('3 enti trovati')
        ->click('[data-organization-type="rifugio"]')
        ->assertScript(organizationsEventually('document.querySelector("[data-organization-results-count]").textContent.includes("1 ente trovato")'), true)
        ->assertAttribute('[data-organization-type="rifugio"]', 'aria-pressed', 'true')
        ->assertSee('Rifugio Il Faro')
        ->assertDontSee('Canile Comunale di Bari')
        ->assertScript('new URLSearchParams(location.search).get("type")', 'rifugio')
        ->click('[data-organization-reset]')
        ->assertScript(organizationsEventually('document.querySelector("[data-organization-results-count]").textContent.includes("3 enti trovati")'), true)
        ->select('#organizations-region', 'Puglia')
        ->assertScript(organizationsEventually('document.querySelector("[data-organization-results-count]").textContent.includes("1 ente trovato")'), true)
        ->assertSee('Canile Comunale di Bari')
        ->assertScript('new URLSearchParams(location.search).get("region")', 'Puglia');
});

it('changes page in place with the shared pagination, on mobile and desktop', function (int $width): void {
    foreach (range(1, 14) as $number) {
        Organization::factory()->verified()->create(['name' => sprintf('Ente %02d', $number), 'region' => 'Lazio']);
    }

    $page = visit(route('organizations.index'))->withLocale('it-IT')->resize($width, 900);

    $page->assertNoJavaScriptErrors()
        ->assertSeeIn('[data-pagination-summary]', 'Pagina 1 di 2')
        ->assertAttribute('[data-pagination-page="1"]', 'aria-current', 'page')
        ->assertScript('document.querySelector("[data-pagination-previous]").getAttribute("aria-disabled")', 'true')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertScript(BrowserScripts::undersizedTargets('[data-pagination]'), [])
        ->click('[data-pagination-page="2"]')
        ->assertScript(organizationsEventually('document.querySelector("[data-pagination-summary]").textContent.includes("Pagina 2 di 2")'), true)
        ->assertAttribute('[data-pagination-page="2"]', 'aria-current', 'page')
        ->assertSee('Ente 13')
        ->assertScript('new URLSearchParams(location.search).get("page")', '2')
        ->assertScript('new URL(document.querySelector("[data-pagination-previous]").href).pathname', '/enti')
        ->assertNoJavaScriptErrors();
})->with(['mobile' => 375, 'desktop' => 1280]);

it('opens a shared link with the old parameter names', function (): void {
    directoryOrganizations();

    visit(route('organizations.index', ['regione' => 'Puglia']))->withLocale('it-IT')
        ->assertScript(organizationsEventually('document.querySelector("[data-organization-results-count]").textContent.includes("1 ente trovato")'), true)
        ->assertSee('Canile Comunale di Bari')
        ->assertValue('#organizations-region', 'Puglia');
});

it('browses the catalog filtered on the organization', function (): void {
    $organization = showcaseOrganization();

    visit(route('organizations.show', $organization))->withLocale('it-IT')
        ->assertAttribute('[data-organization-catalog-link]', 'href', route('catalog', ['organization' => $organization->slug]))
        ->assertScript('getComputedStyle(document.querySelector("#organization-animals-title")).fontSize === getComputedStyle(document.querySelector("#organization-about-title")).fontSize', true);
});

it('picks a preset amount from the keyboard and fills the free amount', function (): void {
    $organization = showcaseOrganization();

    visit(route('organizations.show', $organization))->withLocale('it-IT')
        ->assertNoJavaScriptErrors()
        ->keys('[data-donation-preset="25"]', ['Space'])
        ->assertScript(organizationsEventually('document.querySelector("[data-donation-preset=\'25\']").getAttribute("aria-pressed") === "true"'), true)
        ->assertAttribute('[data-donation-preset="10"]', 'aria-pressed', 'false')
        ->assertValue('#field-donationamount', '25')
        // Un importo libero toglie la selezione dei preimpostati.
        ->type('#field-donationamount', '30')
        ->assertScript(organizationsEventually('document.querySelector("[data-donation-preset=\'25\']").getAttribute("aria-pressed") === "false"'), true);
});

it('moves the focus to the error summary when the donation is invalid, and from there to the field', function (): void {
    $organization = showcaseOrganization();

    visit(route('organizations.show', $organization))->withLocale('it-IT')
        ->keys('[data-donation-preset="10"]', ['Space'])
        ->keys('[data-donation-submit]', ['Enter'])
        ->assertScript(organizationsEventually('document.activeElement?.id === "error-summary"'), true)
        ->assertSeeIn('[data-ui-error-summary]', __('donations.public.validation.email_required'))
        ->assertAttribute('#field-donoremail', 'aria-invalid', 'true')
        ->keys('[data-ui-error-summary] a', ['Enter'])
        ->assertScript(BrowserScripts::activeElement(), 'field-donoremail')
        ->assertNoAccessibilityIssues();
});
