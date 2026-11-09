<?php

declare(strict_types=1);

use App\Enums\Species;
use App\Models\Animal;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Ui\BrowserScripts;

uses(RefreshDatabase::class);

/*
 | Home pubblica (UI-6a) nel browser: accessibilità in chiaro e in scuro, chip della vetrina
 | da tastiera (aggiornano solo l'island, annunciano l'esito e tengono il focus), nessun
 | overflow da 320 px, target ≥ 44 px, header trasparente che diventa solido allo scroll,
 | LCP e CLS. Le foto non servono qui (il file servito richiede le conversioni su disco):
 | priority e alt della foto della hero sono in tests/Feature/Public/HomeTest.php.
 | Gira SOLO nel servizio `browser-tests`.
 */

/** Ripete la condizione JS (ogni 25 ms, al massimo $timeout ms) e restituisce l'ultimo esito. */
function homeEventually(string $condition, int $timeout = 4000): string
{
    return 'function () { return new Promise((resolve) => { const end = Date.now() + '.$timeout.'; const check = () => { let ok = false; try { ok = Boolean('.$condition.'); } catch (error) { ok = false; } if (ok || Date.now() > end) { resolve(ok); } else { setTimeout(check, 25); } }; check(); }); }';
}

/** Animali di specie diverse, in un ente verificato. */
function seedHomeAnimals(): void
{
    $organization = Organization::factory()->verified()->create(['city' => 'Bologna', 'region' => 'Emilia-Romagna']);

    foreach ([['Rex', Species::Dog], ['Argo', Species::Dog], ['Micia', Species::Cat], ['Fiocco', Species::Other]] as [$name, $species]) {
        Animal::factory()->available()->forOrganization($organization)->create(['name' => $name, 'species' => $species, 'featured' => $name === 'Rex']);
    }
}

it('has no accessibility issues in light and dark mode', function (string $device): void {
    seedHomeAnimals();

    $light = visit(route('home'))->withLocale('it-IT');
    $light = $device === 'mobile' ? $light->on()->mobile() : $light;

    $light->assertNoJavaScriptErrors()
        ->assertScript('document.documentElement.classList.contains("dark")', false)
        ->assertNoAccessibilityIssues();

    $dark = visit(route('home'))->inDarkMode()->withLocale('it-IT');
    $dark = $device === 'mobile' ? $dark->on()->mobile() : $dark;

    $dark->assertNoJavaScriptErrors()
        ->assertScript('document.documentElement.classList.contains("dark")', true)
        ->assertNoAccessibilityIssues();
})->with(['desktop', 'mobile']);

it('has no accessibility issues with an empty catalog', function (): void {
    visit(route('home'))->withLocale('it-IT')
        ->assertPresent('[data-home-empty="all"]')
        ->assertNoAccessibilityIssues();
});

it('filters the showcase from the keyboard, keeping the focus and announcing the result', function (): void {
    seedHomeAnimals();

    $page = visit(route('home'))->withLocale('it-IT');

    $page->assertScript('document.querySelector("[data-home-filter=all]").getAttribute("aria-pressed")', 'true')
        ->assertScript('document.querySelectorAll("[data-home-grid] [data-animal-card]").length', 3)
        ->keys('[data-home-filter="cat"]', ['Enter'])
        ->assertScript(homeEventually('document.querySelector("[data-home-filter=cat]").getAttribute("aria-pressed") === "true"'), true)
        ->assertScript(homeEventually('document.querySelectorAll("[data-home-grid] [data-animal-card]").length === 1'), true)
        ->assertScript('document.querySelector("[data-home-grid] [data-card-link]").textContent.trim()', 'Micia')
        ->assertScript('document.querySelector("[data-home-filter=all]").getAttribute("aria-pressed")', 'false')
        ->assertScript('document.activeElement.dataset.homeFilter', 'cat')
        ->assertScript('document.querySelector("[data-home-results]").textContent.trim()', 'Un animale in vetrina: Gatti')
        ->assertScript('new URL(document.querySelector("[data-home-cta] a").href).search', '?species=cat')
        ->keys('[data-home-filter="all"]', ['Space'])
        ->assertScript(homeEventually('document.querySelectorAll("[data-home-grid] [data-animal-card]").length === 3'), true)
        ->assertNoJavaScriptErrors();
});

it('searches the catalog from the hero', function (): void {
    seedHomeAnimals();

    visit(route('home'))->withLocale('it-IT')
        // Label visibile (non solo per gli screen reader), collegata al campo.
        ->assertScript('document.querySelector("label[for=home-search]").checkVisibility()', true)
        ->assertScript('document.querySelector("label[for=home-search]").getBoundingClientRect().width > 1', true)
        ->type('[data-home-search] input[name=q]', 'Micia')
        ->keys('[data-home-search] input[name=q]', ['Enter'])
        ->assertScript(homeEventually('location.pathname === "/animali" && new URLSearchParams(location.search).get("q") === "Micia"'), true);
});

it('has no horizontal overflow at 320 px and keeps every target at least 44 px', function (string $scheme, string $locale): void {
    seedHomeAnimals();

    $page = $scheme === 'dark' ? visit(route('home'))->inDarkMode() : visit(route('home'));

    $page->withLocale($locale)->resize(320, 800)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertScript(BrowserScripts::undersizedTargets('main'), []);
})->with([
    'light, Italian' => ['light', 'it-IT'],
    'dark, English' => ['dark', 'en-US'],
]);

it('keeps the targets at least 44 px on desktop', function (): void {
    seedHomeAnimals();

    visit(route('home'))->withLocale('it-IT')->resize(1440, 900)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertScript(BrowserScripts::undersizedTargets('main'), []);
});

it('starts with a transparent header over the hero that turns solid when scrolling', function (): void {
    seedHomeAnimals();

    $page = visit(route('home'))->withLocale('it-IT');

    $page->assertScript('document.querySelector("[data-site-header]").dataset.transparent', 'true')
        ->assertScript('getComputedStyle(document.querySelector("[data-site-header]")).backgroundColor', 'rgba(0, 0, 0, 0)')
        // La hero lascia lo spazio dell'header: il blocco giallo comincia sotto di esso.
        ->assertScript('document.querySelector("[data-home-hero-block]").getBoundingClientRect().top >= document.querySelector("[data-site-header]").getBoundingClientRect().bottom', true);

    $page->script('window.scrollTo(0, 800)');
    $page->assertScript(homeEventually('document.querySelector("[data-site-header]").dataset.scrolled === "true"'), true)
        ->assertScript(homeEventually('getComputedStyle(document.querySelector("[data-site-header]")).backgroundColor !== "rgba(0, 0, 0, 0)"'), true);

    $page->script('window.scrollTo(0, 0)');
    $page->assertScript(homeEventually('document.querySelector("[data-site-header]").dataset.scrolled === undefined'), true);
});

it('keeps LCP and CLS within budget', function (): void {
    seedHomeAnimals();

    $page = visit(route('home'))->withLocale('it-IT')->on()->mobile();

    $metrics = <<<'JS'
        () => new Promise((resolve) => {
            let lcp = 0;
            let cls = 0;
            new PerformanceObserver((list) => { for (const entry of list.getEntries()) { lcp = entry.startTime; } }).observe({ type: 'largest-contentful-paint', buffered: true });
            new PerformanceObserver((list) => { for (const entry of list.getEntries()) { if (!entry.hadRecentInput) { cls += entry.value; } } }).observe({ type: 'layout-shift', buffered: true });
            setTimeout(() => resolve(lcp > 0 && lcp <= 2500 && cls <= 0.1 ? 'ok' : `lcp=${Math.round(lcp)} cls=${cls.toFixed(3)}`), 1000);
        })
        JS;

    $page->assertScript($metrics, 'ok');
});
