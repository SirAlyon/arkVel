<?php

declare(strict_types=1);

use Tests\Support\Ui\BrowserScripts;

/*
 | Componenti x-ui del gruppo "domain" nel browser: accessibilità (axe), tastiera,
 | target ≥ 44 px, link esteso delle card, nomi dei preferiti, gerarchia dei titoli.
 | Pagina di prova: /_styleguide/domain (solo local).
 | Gira SOLO nel servizio `browser-tests`: `docker compose --profile testing run --rm browser-tests`.
 */

beforeEach(function (): void {
    app()->detectEnvironment(static fn (): string => 'local');
});

/**
 * Elementi raggiungibili con Tab dentro una card animale, come "tag[data-*]".
 */
function domainCardTabStops(string $card): string
{
    $card = json_encode($card, JSON_THROW_ON_ERROR);

    return <<<JS
        [...document.querySelector({$card}).querySelectorAll('a[href], button, input, select, textarea, [tabindex]:not([tabindex="-1"])')]
            .filter((element) => element.checkVisibility())
            .map((element) => element.hasAttribute('data-card-link') ? 'link' : (element.hasAttribute('data-favorite') ? 'favorite' : element.tagName.toLowerCase()))
        JS;
}

it('has no accessibility issues in light and dark mode', function (): void {
    visit(route('styleguide.group', 'domain'))->withLocale('it-IT')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();

    visit(route('styleguide.group', 'domain'))->inDarkMode()->withLocale('it-IT')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});

it('keeps every control at least 44 px on mobile and desktop, without horizontal overflow', function (): void {
    $components = '[data-styleguide-component]';

    visit(route('styleguide.group', 'domain'))->withLocale('it-IT')
        ->assertScript(BrowserScripts::undersizedTargets($components), []);

    visit(route('styleguide.group', 'domain'))->withLocale('it-IT')->on()->mobile()
        ->assertScript(BrowserScripts::undersizedTargets($components), [])
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
});

it('has a heading hierarchy without skipped levels', function (): void {
    $skips = <<<'JS'
        [...document.querySelectorAll('h1, h2, h3, h4, h5, h6')]
            .filter((heading) => heading.checkVisibility())
            .map((heading) => Number(heading.tagName[1]))
            .flatMap((level, index, levels) => index > 0 && level > levels[index - 1] + 1 ? [`h${levels[index - 1]}→h${level}`] : [])
        JS;

    visit(route('styleguide.group', 'domain'))->withLocale('it-IT')
        ->assertScript($skips, [])
        ->assertScript("document.querySelectorAll('h1').length", 1);
});

it('reaches only the name link and the favorite button inside an animal card', function (): void {
    visit(route('styleguide.group', 'domain'))->withLocale('it-IT')
        ->assertScript(domainCardTabStops('[data-demo-animal="fido"]'), ['favorite', 'link'])
        ->assertScript(domainCardTabStops('[data-demo-animal="zeus"]'), ['link'])
        ->assertScript(domainCardTabStops('[data-demo="animal-cta"]'), ['a'])
        ->assertScript("document.querySelectorAll('[data-demo=\"organization-grid\"] [data-organization-card] a[href]').length", 3);
});

it('gives every favorite button a distinct name containing the animal name', function (): void {
    $names = "[...document.querySelectorAll('[data-demo=\"animal-grid\"] [data-favorite]')].map((button) => button.getAttribute('aria-label'))";

    visit(route('styleguide.group', 'domain'))->withLocale('it-IT')
        ->assertScript($names, ['Salva Fido nei preferiti', 'Salva Luna nei preferiti', 'Salva Birba nei preferiti']);

    visit(route('styleguide.group', 'domain'))->withLocale('en-US')
        ->assertScript($names, ['Save Fido to favorites', 'Save Luna to favorites', 'Save Birba to favorites']);
});

it('toggles a favorite from the keyboard without following the card link', function (): void {
    $favorite = '[data-demo-animal="luna"] [data-favorite]';

    visit(route('styleguide.group', 'domain'))->withLocale('it-IT')
        ->assertAttribute($favorite, 'aria-pressed', 'false')
        ->keys($favorite, ['Enter'])
        ->assertAttribute($favorite, 'aria-pressed', 'true')
        ->assertAttribute($favorite, 'aria-label', 'Salva Luna nei preferiti')
        ->assertScript('window.location.hash', '')
        ->keys($favorite, ['Space'])
        ->assertAttribute($favorite, 'aria-pressed', 'false')
        ->assertAttribute($favorite, 'aria-label', 'Salva Luna nei preferiti');
});

it('makes the whole animal card clickable through the name link', function (): void {
    $card = '[data-demo-animal="birba"]';

    visit(route('styleguide.group', 'domain'))->withLocale('it-IT')
        // Un punto della card lontano dal nome (il centro della foto) apre comunque il link.
        ->assertScript(<<<JS
            (() => {
                document.querySelector('{$card}').scrollIntoView({ block: 'center' });
                const media = document.querySelector('{$card} [data-animal-card-media]').getBoundingClientRect();
                const hit = document.elementFromPoint(media.left + media.width / 2, media.top + media.height / 2);
                return hit?.closest('a')?.hasAttribute('data-card-link') ?? false;
            })()
            JS, true)
        // Il bottone preferiti resta sopra il link esteso.
        ->assertScript(<<<'JS'
            (() => {
                const button = document.querySelector('[data-demo-animal="fido"] [data-favorite]');
                button.scrollIntoView({ block: 'center' });
                const box = button.getBoundingClientRect();
                return document.elementFromPoint(box.left + box.width / 2, box.top + box.height / 2)?.closest('[data-favorite]') === button;
            })()
            JS, true);
});

it('shows a visible focus ring on the card link', function (): void {
    visit(route('styleguide.group', 'domain'))->withLocale('it-IT')
        ->keys('[data-demo-animal="fido"] [data-favorite]', ['Tab'])
        ->assertScript("document.activeElement.hasAttribute('data-card-link')", true)
        ->assertScript('document.activeElement.textContent.trim()', 'Fido')
        ->assertScript('getComputedStyle(document.activeElement).outlineStyle', 'solid')
        ->assertScript('getComputedStyle(document.activeElement).outlineWidth', '3px');
});
