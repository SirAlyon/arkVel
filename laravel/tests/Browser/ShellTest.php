<?php

declare(strict_types=1);

use App\Models\Animal;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Tests\Support\Ui\BrowserScripts;

uses(RefreshDatabase::class);

/*
 | Shell del sito pubblico (UI-3) nel browser: accessibilità in chiaro e (con l'opt-in
 | themeable) in scuro, menu mobile da tastiera, cambio lingua, nessun overflow a 320 px,
 | navigazione wire:navigate (senza ricaricare, annuncio, toaster unico), header trasparente
 | che diventa solido allo scroll.
 |
 | Gli opt-in di layout (themeable, transparentHeader) non li usa ancora nessuna pagina
 | (arrivano con UI-6): li prova una pagina di servizio registrata dal test, resa con il
 | layout vero come la renderebbe Livewire.
 | Gira SOLO nel servizio `browser-tests`: `docker compose --profile testing run --rm browser-tests`.
 */

/**
 * Registra una pagina di prova che usa layouts::app con i dati di layout indicati.
 *
 * @param  array<string, mixed>  $data
 */
function shellFixture(string $path, array $data = []): string
{
    Route::middleware('web')->get($path, static fn (): string => Blade::render(
        <<<'BLADE'
            @component('layouts::app', $data)
                <section class="mx-auto flex max-w-page flex-col gap-6 px-gutter pt-24" style="min-height: 200vh">
                    <h1 class="text-h1">Shell</h1>
                    <p><a href="/" wire:navigate>Home</a></p>
                </section>
            @endcomponent
            BLADE,
        ['data' => [...$data, 'title' => 'Shell — ArkVel']],
    ));

    return $path;
}

/** Violazioni axe critical/serious dentro header e footer (le pagine legacy migrano in UI-6). */
function shellAxeViolations(): string
{
    return <<<'JS'
        async () => (await window.axe.run({ include: [['[data-site-header]'], ['[data-site-footer]']] }, { resultTypes: ['violations'] }))
            .violations
            .filter((violation) => ['critical', 'serious'].includes(violation.impact))
            .map((violation) => `${violation.id}: ${violation.nodes.map((node) => node.target.join(' ')).join(', ')}`)
        JS;
}

/** Ripete la condizione JS (ogni 25 ms, al massimo $timeout ms) e restituisce l'ultimo esito. */
function shellEventually(string $condition, int $timeout = 4000): string
{
    return 'function () { return new Promise((resolve) => { const end = Date.now() + '.$timeout.'; const check = () => { let ok = false; try { ok = Boolean('.$condition.'); } catch (error) { ok = false; } if (ok || Date.now() > end) { resolve(ok); } else { setTimeout(check, 25); } }; check(); }); }';
}

it('has no serious accessibility issues in the shell of a legacy page', function (): void {
    visit(route('home'))->withLocale('it-IT')
        ->assertNoJavaScriptErrors()
        ->assertScript(shellAxeViolations(), []);

    visit(route('catalog'))->withLocale('it-IT')->on()->mobile()
        ->assertNoJavaScriptErrors()
        ->assertScript(shellAxeViolations(), []);
});

it('has no accessibility issues on a themeable page, in light and dark mode', function (): void {
    $path = shellFixture('/_shell-themeable', ['themeable' => true]);

    visit($path)->withLocale('it-IT')
        ->assertScript('document.documentElement.classList.contains("dark")', false)
        ->assertNoAccessibilityIssues();

    visit($path)->inDarkMode()->withLocale('it-IT')
        ->assertScript('document.documentElement.classList.contains("dark")', true)
        ->assertScript('document.querySelector("[data-site-footer] [data-theme-option=system]").getAttribute("aria-pressed")', 'true')
        ->assertNoAccessibilityIssues();
});

it('keeps legacy pages light even when the system prefers dark', function (): void {
    visit(route('home'))->inDarkMode()->withLocale('it-IT')
        ->assertScript('document.documentElement.dataset.themeForced', 'light')
        ->assertScript('document.documentElement.classList.contains("dark")', false)
        ->assertScript('document.querySelector("[data-theme-option]")', null);
});

it('opens and closes the mobile menu from the keyboard', function (): void {
    $page = visit(route('home'))->withLocale('it-IT')->on()->mobile();

    $page->assertScript('document.querySelector("[data-site-nav]").checkVisibility()', false)
        ->keys('[data-site-menu-trigger]', ['Enter'])
        ->assertScript(shellEventually('document.querySelector("dialog[data-site-menu]").open'), true)
        ->assertScript('document.querySelector("dialog[data-site-menu]").contains(document.activeElement)', true)
        ->assertScript(BrowserScripts::undersizedTargets('dialog[data-site-menu]'), [])
        ->assertScript('[...document.querySelectorAll("dialog[data-site-menu] [data-nav-item]")].map((link) => link.textContent.trim())', ['Catalogo', 'Enti'])
        ->assertScript('document.querySelectorAll("dialog[data-site-menu] [data-locale-option]").length', 2)
        ->keys(':focus', ['Escape'])
        ->assertScript(shellEventually('!document.querySelector("dialog[data-site-menu]").open'), true)
        ->assertScript('document.activeElement.hasAttribute("data-site-menu-trigger")', true);
});

it('switches language from the header', function (): void {
    visit(route('catalog'))->withLocale('it-IT')
        ->assertScript('document.documentElement.lang', 'it')
        ->click('[data-header-language] [data-locale-option="en"]')
        ->assertScript(shellEventually('document.documentElement.lang === "en"'), true)
        ->assertTitle('Catalog — ArkVel')
        ->assertScript('document.querySelector("[data-header-language] [data-locale-option=en]").getAttribute("aria-pressed")', 'true')
        ->assertScript('document.querySelector("[data-header-language] [data-locale-option=it]").getAttribute("aria-pressed")', 'false');
});

it('has no horizontal overflow at 320 px', function (string $route): void {
    visit(route($route))->withLocale('it-IT')->resize(320, 800)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertScript(BrowserScripts::undersizedTargets('[data-site-header]'), []);
})->with(['home', 'catalog', 'organizations.index']);

it('navigates between pages without a full reload, announcing the new page', function (): void {
    $organization = Organization::factory()->verified()->create();
    Animal::factory()->available()->forOrganization($organization)->create(['name' => 'Fiocco']);

    $page = visit(route('home'))->withLocale('it-IT');

    // Un segno sulla finestra sopravvive solo se la pagina non si ricarica.
    $page->script('window.shellMarker = "same-document"');

    $page->click('[data-site-nav] [data-nav-item="catalog"]')
        ->assertScript(shellEventually('location.pathname === "/animali" && document.title === "Catalogo — ArkVel"'), true)
        ->assertScript('window.shellMarker', 'same-document')
        ->assertScript('document.querySelector("[data-site-nav] [data-nav-item=catalog]").getAttribute("aria-current")', 'page')
        ->assertScript(shellEventually('document.querySelector("[data-route-announcer]").textContent === "Pagina caricata: Catalogo — ArkVel"'), true)
        ->assertSee('Fiocco')
        ->assertNoJavaScriptErrors();

    // Store e listener registrati una volta sola: un toast dopo la navigazione è uno solo.
    $page->script('window.dispatchEvent(new CustomEvent("toast", { detail: { type: "success", title: "Salvato" } }))');
    $page->assertScript(shellEventually('document.querySelectorAll("[data-toaster] [data-toast]").length === 1'), true)
        ->assertScript('document.querySelectorAll("[data-toaster]").length', 1);
});

it('starts keyboard navigation again from the skip link after a wire:navigate', function (): void {
    $page = visit(route('home'))->withLocale('it-IT');

    $page->click('[data-site-nav] [data-nav-item="catalog"]')
        ->assertScript(shellEventually('location.pathname === "/animali" && document.querySelector("[data-route-announcer]").textContent === "Pagina caricata: Catalogo — ArkVel"'), true)
        // `html > body` (un selettore CSS esplicito: `body` da solo Pest lo cercherebbe come
        // testo) non è focalizzabile, quindi il Tab parte da dove la navigazione ha lasciato
        // il focus.
        ->keys('html > body', ['Tab'])
        ->assertScript('document.activeElement.hasAttribute("data-skip-link")', true)
        ->assertScript('document.activeElement.checkVisibility()', true);
});

it('shows the sign-up button in the header only from lg', function (int $width, bool $visible): void {
    visit(route('home'))->withLocale('it-IT')->resize($width, 900)
        ->assertScript('document.querySelector("[data-header-account] [data-account-login]").checkVisibility()', true)
        ->assertScript('document.querySelector("[data-header-account] [data-account-register]").checkVisibility()', $visible);
})->with([
    'tablet (md)' => [768, false],
    'desktop (lg)' => [1024, true],
]);

it('colors the navigation progress bar with the primary token', function (): void {
    visit(route('home'))->withLocale('it-IT')
        ->assertScript('getComputedStyle(document.documentElement).getPropertyValue("--livewire-progress-bar-color").trim() === getComputedStyle(document.documentElement).getPropertyValue("--color-primary").trim()', true);
});

it('applies the theme of each page while navigating', function (): void {
    $path = shellFixture('/_shell-themeable', ['themeable' => true]);

    $page = visit(route('home'))->inDarkMode()->withLocale('it-IT');

    $page->assertScript('document.documentElement.classList.contains("dark")', false);
    $page->script('Livewire.navigate('.json_encode($path).')');
    $page->assertScript(shellEventually('location.pathname === '.json_encode($path).' && document.documentElement.classList.contains("dark")'), true)
        ->assertScript('document.documentElement.dataset.themeForced ?? null', null);

    $page->click('[data-site-header] [data-site-logo]')
        ->assertScript(shellEventually('location.pathname === "/" && !document.documentElement.classList.contains("dark")'), true)
        ->assertScript('document.documentElement.dataset.themeForced', 'light');
});

it('turns the transparent header solid when the page scrolls', function (): void {
    $path = shellFixture('/_shell-transparent', ['transparentHeader' => true]);

    $page = visit($path)->withLocale('it-IT');

    $page->assertScript('document.querySelector("[data-site-header]").dataset.scrolled ?? null', null)
        ->assertScript('getComputedStyle(document.querySelector("[data-site-header]")).backgroundColor', 'rgba(0, 0, 0, 0)');

    $page->script('window.scrollTo(0, 600)');
    $page->assertScript(shellEventually('document.querySelector("[data-site-header]").dataset.scrolled === "true"'), true)
        ->assertScript(shellEventually('getComputedStyle(document.querySelector("[data-site-header]")).backgroundColor !== "rgba(0, 0, 0, 0)"'), true)
        ->assertScript('document.querySelector("[data-site-header]").getBoundingClientRect().top', 0);

    $page->script('window.scrollTo(0, 0)');
    $page->assertScript(shellEventually('document.querySelector("[data-site-header]").dataset.scrolled === undefined'), true);
});
