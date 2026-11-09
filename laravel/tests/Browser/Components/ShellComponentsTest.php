<?php

declare(strict_types=1);

use Tests\Support\Ui\BrowserScripts;

/*
 | Styleguide · gruppo "shell" nel browser: accessibilità (axe) in chiaro e in scuro,
 | target, nessun overflow a 320 px, menu mobile dell'anteprima dell'header da tastiera.
 | Pagina di prova: /_styleguide/shell (solo local). La shell sulle pagine vere:
 | tests/Browser/ShellTest.php.
 | Gira SOLO nel servizio `browser-tests`: `docker compose --profile testing run --rm browser-tests`.
 */

beforeEach(function (): void {
    app()->detectEnvironment(static fn (): string => 'local');
});

/** Ripete la condizione JS (ogni 25 ms, al massimo 4 s) e restituisce l'ultimo esito. */
function shellDemoEventually(string $condition): string
{
    return 'function () { return new Promise((resolve) => { const end = Date.now() + 4000; const check = () => { let ok = false; try { ok = Boolean('.$condition.'); } catch (error) { ok = false; } if (ok || Date.now() > end) { resolve(ok); } else { setTimeout(check, 25); } }; check(); }); }';
}

it('has no accessibility issues in light and dark mode', function (): void {
    visit(route('styleguide.group', 'shell'))->withLocale('it-IT')
        ->assertNoJavaScriptErrors()
        ->assertScript('document.documentElement.classList.contains("dark")', false)
        ->assertNoAccessibilityIssues();

    visit(route('styleguide.group', 'shell'))->inDarkMode()->withLocale('it-IT')
        ->assertNoJavaScriptErrors()
        ->assertScript('document.documentElement.classList.contains("dark")', true)
        ->assertNoAccessibilityIssues();
});

it('keeps the controls large enough, without horizontal overflow at 320 px', function (): void {
    $controls = '[data-demo^="nav-"], [data-demo^="language-"], [data-demo="site-header"]';

    visit(route('styleguide.group', 'shell'))->withLocale('it-IT')
        ->assertScript(BrowserScripts::undersizedTargets($controls), [])
        ->assertScript(BrowserScripts::undersizedTargets('[data-demo="site-footer"]', 24), []);

    visit(route('styleguide.group', 'shell'))->withLocale('it-IT')->resize(320, 800)
        ->assertScript(BrowserScripts::undersizedTargets($controls), [])
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
});

it('shows the guest header and opens its mobile menu from the keyboard', function (): void {
    $page = visit(route('styleguide.group', 'shell'))->withLocale('it-IT')->on()->mobile();

    $page->assertScript('document.querySelector("[data-demo=site-header] [data-account-guest]") !== null', true)
        ->keys('[data-demo="site-header"] [data-site-menu-trigger]', ['Enter'])
        ->assertScript(shellDemoEventually('document.querySelector("dialog[data-site-menu]").open'), true)
        ->assertScript('document.querySelector("dialog[data-site-menu]").contains(document.activeElement)', true)
        ->keys(':focus', ['Escape'])
        ->assertScript(shellDemoEventually('!document.querySelector("dialog[data-site-menu]").open'), true)
        ->assertScript('document.activeElement.hasAttribute("data-site-menu-trigger")', true);
});

it('marks the current page and the current section with aria-current', function (): void {
    $state = '[...document.querySelectorAll("[data-demo=nav-pill] [data-nav-link], [data-demo=nav-section] [data-nav-link]")].map((link) => link.getAttribute("aria-current"))';

    visit(route('styleguide.group', 'shell'))->withLocale('it-IT')
        ->assertScript($state, ['page', null, null, 'true']);
});
