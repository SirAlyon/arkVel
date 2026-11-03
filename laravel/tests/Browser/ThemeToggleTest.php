<?php

declare(strict_types=1);

/*
 | Dark mode (UI-1): la classe `.dark` segue la scelta salvata o prefers-color-scheme,
 | e la scelta sopravvive al ricaricamento. Pagina di prova: /_styleguide (solo local).
 | Gira SOLO nel servizio `browser-tests`: `docker compose --profile testing run --rm browser-tests`.
 */

beforeEach(function (): void {
    app()->detectEnvironment(static fn (): string => 'local');
});

it('follows the system preference by default', function (): void {
    visit(route('styleguide'))
        ->inDarkMode()
        ->withLocale('it-IT')
        ->assertScript('document.documentElement.classList.contains("dark")', true)
        ->assertScript('document.querySelector("[data-theme-option=system]").getAttribute("aria-pressed")', 'true')
        ->assertNoJavaScriptErrors();
});

it('persists the chosen theme across reloads', function (): void {
    $page = visit(route('styleguide'))->withLocale('it-IT');

    $page->assertScript('document.documentElement.classList.contains("dark")', false)
        ->click('[data-theme-option="dark"]')
        ->assertScript('document.documentElement.classList.contains("dark")', true)
        ->assertScript('localStorage.getItem("arkvel-theme")', 'dark')
        ->refresh()
        ->assertScript('document.documentElement.classList.contains("dark")', true)
        ->assertScript('document.querySelector("[data-theme-option=dark]").getAttribute("aria-pressed")', 'true')
        ->click('[data-theme-option="light"]')
        ->assertScript('document.documentElement.classList.contains("dark")', false);
});

it('passes every contrast pair in both themes', function (): void {
    $failures = 'document.querySelectorAll("[data-contrast-result].text-danger").length';
    $checked = 'document.querySelectorAll("[data-contrast-result].text-success").length';

    $theme = 'document.querySelector("[data-contrast-table]").dataset.contrastTheme';

    visit(route('styleguide'))->withLocale('it-IT')
        ->assertScript($theme, 'light')
        ->assertScript($failures, 0)
        ->assertScript($checked.' > 0', true)
        ->click('[data-theme-option="dark"]')
        ->assertScript($theme, 'dark')
        ->assertScript($failures, 0)
        ->assertScript($checked.' > 0', true);
});

it('has no serious accessibility issues', function (): void {
    visit(route('styleguide'))->withLocale('it-IT')->assertNoAccessibilityIssues();
    visit(route('styleguide'))->inDarkMode()->withLocale('it-IT')->assertNoAccessibilityIssues();
});

it('updates the toggle when the theme changes elsewhere', function (): void {
    // window.arkvelTheme.set() emette `arkvel-theme`: lo stesso evento che riceve un
    // secondo selettore sulla pagina o una scheda aggiornata via `storage`.
    $page = visit(route('styleguide'))->withLocale('it-IT');

    $page->assertScript('document.querySelector("[data-theme-option=system]").getAttribute("aria-pressed")', 'true');
    $page->script('window.arkvelTheme.set("dark")');
    $page->assertScript('document.querySelector("[data-theme-option=dark]").getAttribute("aria-pressed")', 'true')
        ->assertScript('document.querySelector("[data-theme-option=system]").getAttribute("aria-pressed")', 'false')
        ->assertScript('document.documentElement.classList.contains("dark")', true);
});
