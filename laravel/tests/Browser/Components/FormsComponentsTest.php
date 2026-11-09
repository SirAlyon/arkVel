<?php

declare(strict_types=1);

use Tests\Support\Ui\BrowserScripts;

/*
 | Componenti x-ui del gruppo "forms" nel browser: accessibilità (axe), tastiera,
 | target ≥ 44 px, focus, contatore, stato misto, modulo d'esempio con errori.
 | Pagina di prova: /_styleguide/forms (solo local).
 | Gira SOLO nel servizio `browser-tests`: `docker compose --profile testing run --rm browser-tests`.
 */

beforeEach(function (): void {
    app()->detectEnvironment(static fn (): string => 'local');
});

it('has no accessibility issues in light and dark mode', function (): void {
    visit(route('styleguide.group', 'forms'))->withLocale('it-IT')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();

    visit(route('styleguide.group', 'forms'))->inDarkMode()->withLocale('it-IT')
        ->assertNoAccessibilityIssues();
});

it('has no accessibility issues with the example form errors', function (): void {
    visit(route('styleguide.group', ['forms', 'demo' => 'errors']))->withLocale('it-IT')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();

    visit(route('styleguide.group', ['forms', 'demo' => 'errors']))->inDarkMode()->withLocale('it-IT')
        ->assertNoAccessibilityIssues();
});

it('keeps every control at least 44 px on mobile and desktop', function (): void {
    $components = '[data-styleguide-component]';

    visit(route('styleguide.group', 'forms'))->withLocale('it-IT')
        ->assertScript(BrowserScripts::undersizedTargets($components), []);

    visit(route('styleguide.group', 'forms'))->withLocale('it-IT')->on()->mobile()
        ->assertScript(BrowserScripts::undersizedTargets($components), [])
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
});

it('draws the focus ring around the input pill', function (): void {
    visit(route('styleguide.group', 'forms'))->withLocale('it-IT')
        ->keys('[data-demo="password"] [data-password-toggle]', ['Shift+Tab'])
        ->assertScript(BrowserScripts::activeElement(), 'demo-password')
        ->assertScript('getComputedStyle(document.activeElement.closest("[data-ui-input]")).outlineStyle', 'solid')
        ->assertScript('getComputedStyle(document.activeElement.closest("[data-ui-input]")).outlineWidth', '3px');
});

it('shows and hides the password from the keyboard', function (): void {
    $toggle = '[data-demo="password"] [data-password-toggle]';

    visit(route('styleguide.group', 'forms'))->withLocale('it-IT')
        ->assertAttribute('#demo-password', 'type', 'password')
        ->assertAttribute($toggle, 'aria-pressed', 'false')
        ->keys($toggle, ['Enter'])
        ->assertAttribute($toggle, 'aria-pressed', 'true')
        ->assertAttribute('#demo-password', 'type', 'text')
        ->assertAttribute($toggle, 'aria-label', 'Mostra password')
        ->keys($toggle, ['Space'])
        ->assertAttribute('#demo-password', 'type', 'password');
});

it('counts characters and announces when the limit is near', function (): void {
    visit(route('styleguide.group', 'forms'))->withLocale('it-IT')
        ->assertSeeIn('[data-demo="counter"] [data-counter]', '38 / 120')
        ->fill('#demo-counter', str_repeat('a', 115))
        ->assertSeeIn('[data-demo="counter"] [data-counter]', '115 / 120')
        ->wait(1)
        ->assertSeeIn('[data-demo="counter"] [data-counter-status]', 'Restano 5 caratteri')
        ->fill('#demo-counter', str_repeat('a', 130))
        ->assertSeeIn('[data-demo="counter"] [data-counter]', '120 / 120')
        ->wait(1)
        ->assertSeeIn('[data-demo="counter"] [data-counter-status]', 'Hai raggiunto il limite di 120 caratteri');
});

it('toggles checkboxes and switches from the label and the keyboard', function (): void {
    visit(route('styleguide.group', 'forms'))->withLocale('it-IT')
        ->assertNotChecked('#field-checkbox-updates')
        ->click('Avvisami quando arriva un animale compatibile')
        ->assertChecked('#field-checkbox-updates')
        ->keys('#field-checkbox-updates', ['Space'])
        ->assertNotChecked('#field-checkbox-updates')
        ->assertNotChecked('#demo-switch')
        ->keys('#demo-switch', ['Space'])
        ->assertChecked('#demo-switch')
        ->assertScript('getComputedStyle(document.activeElement).outlineWidth', '3px');
});

it('keeps the select all checkbox in the mixed state', function (): void {
    visit(route('styleguide.group', 'forms'))->withLocale('it-IT')
        ->assertIndeterminate('#demo-select-all')
        ->assertIndeterminate('[data-demo="indeterminate"]')
        ->click('#demo-select-all')
        ->assertChecked('#demo-select-all-dogs')
        ->assertChecked('#demo-select-all-cats')
        ->assertChecked('#demo-select-all-others')
        ->assertScript('document.querySelector("#demo-select-all").indeterminate', false)
        ->click('#demo-select-all-cats')
        ->assertIndeterminate('#demo-select-all')
        ->assertNotChecked('#demo-select-all-cats');
});

it('moves through a radio group with the arrow keys', function (): void {
    visit(route('styleguide.group', 'forms'))->withLocale('it-IT')
        ->assertChecked('#field-radio-size-medium')
        ->keys('#field-radio-size-medium', ['ArrowDown'])
        ->assertChecked('#field-radio-size-large')
        ->assertNotChecked('#field-radio-size-medium')
        ->assertScript(BrowserScripts::activeElement(), 'field-radio-size-large')
        ->keys('#field-radio-size-large', ['ArrowDown'])
        ->assertChecked('#field-radio-size-small')
        ->assertNotChecked('#field-radio-size-giant');
});

it('shows the errors of the example form with a summary that leads to each field', function (): void {
    visit(route('styleguide.group', 'forms'))->withLocale('it-IT')
        ->assertMissing('[data-demo="error-summary"]')
        ->click('Invia candidatura')
        ->assertPresent('[data-demo="error-summary"]')
        ->assertQueryStringHas('demo', 'errors')
        ->assertScript('document.activeElement.dataset.demo', 'error-summary')
        ->assertAttribute('#apply-email', 'aria-invalid', 'true')
        ->assertAttribute('#apply-email', 'aria-describedby', 'apply-email-help apply-email-error')
        ->assertSeeIn('#apply-privacy-error', 'Per inviare la candidatura devi accettare l’informativa privacy.')
        ->click('[data-demo="error-summary"] a[href="#apply-email"]')
        ->assertScript(BrowserScripts::activeElement(), 'apply-email');
});
