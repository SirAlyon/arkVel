<?php

declare(strict_types=1);

use Tests\Support\Ui\BrowserScripts;

/*
 | Componenti x-ui del gruppo "actions" nel browser: accessibilità (axe), tastiera,
 | target ≥ 44 px, stati. Pagina di prova: /_styleguide/actions (solo local).
 | Gira SOLO nel servizio `browser-tests`: `docker compose --profile testing run --rm browser-tests`.
 */

beforeEach(function (): void {
    app()->detectEnvironment(static fn (): string => 'local');
});

it('has no accessibility issues in light and dark mode', function (): void {
    visit(route('styleguide.group', 'actions'))->withLocale('it-IT')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();

    visit(route('styleguide.group', 'actions'))->inDarkMode()->withLocale('it-IT')
        ->assertNoAccessibilityIssues();
});

it('keeps every control at least 44 px on mobile and desktop', function (): void {
    $components = '[data-styleguide-component]';

    visit(route('styleguide.group', 'actions'))->withLocale('it-IT')
        ->assertScript(BrowserScripts::undersizedTargets($components), []);

    visit(route('styleguide.group', 'actions'))->withLocale('it-IT')->on()->mobile()
        ->assertScript(BrowserScripts::undersizedTargets($components), [])
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
});

it('toggles filter chips from the keyboard', function (): void {
    visit(route('styleguide.group', 'actions'))->withLocale('it-IT')
        ->assertAttribute('[data-chip="all"]', 'aria-pressed', 'true')
        ->keys('[data-chip="cats"]', ['Enter'])
        ->assertAttribute('[data-chip="cats"]', 'aria-pressed', 'true')
        ->assertAttribute('[data-chip="all"]', 'aria-pressed', 'false')
        ->keys('[data-chip="dogs"]', ['Space'])
        ->assertAttribute('[data-chip="dogs"]', 'aria-pressed', 'true')
        ->assertAttribute('[data-chip="cats"]', 'aria-pressed', 'false');
});

it('removes a tag with its named button', function (): void {
    visit(route('styleguide.group', 'actions'))->withLocale('it-IT')
        ->assertAttribute('[data-remove="dogs"]', 'aria-label', 'Rimuovi Cani')
        ->keys('[data-remove="dogs"]', ['Enter'])
        ->assertMissing('[data-remove="dogs"]')
        ->assertVisible('[data-remove="cats"]');
});

it('shows the spinner while a button is loading', function (): void {
    $spinner = '[data-demo="data-loading"] [data-button-spinner]';

    visit(route('styleguide.group', 'actions'))->withLocale('it-IT')
        ->assertMissing($spinner)
        ->click('[data-demo="data-loading"]')
        ->assertVisible($spinner)
        ->assertVisible('[data-demo="loading"] [data-button-spinner]')
        ->assertButtonDisabled('[data-demo="loading"]');
});

it('shows a visible focus ring on buttons', function (): void {
    visit(route('styleguide.group', 'actions'))->withLocale('it-IT')
        ->keys('[data-chip="dogs"]', ['Shift+Tab'])
        ->assertScript(BrowserScripts::activeElement('data-chip'), 'all')
        ->assertScript('getComputedStyle(document.activeElement).outlineStyle', 'solid')
        ->assertScript('getComputedStyle(document.activeElement).outlineWidth', '3px');
});
