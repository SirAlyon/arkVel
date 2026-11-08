<?php

declare(strict_types=1);

use Tests\Support\Ui\BrowserScripts;

/*
 | Componenti x-ui del gruppo "content" nel browser: accessibilità (axe), tastiera (tabs e
 | accordion secondo APG), target ≥ 44 px, sincronizzazione x-modelable, fallback degli avatar.
 | Pagina di prova: /_styleguide/content (solo local).
 | Gira SOLO nel servizio `browser-tests`: `docker compose --profile testing run --rm browser-tests`.
 */

beforeEach(function (): void {
    app()->detectEnvironment(static fn (): string => 'local');
});

it('has no accessibility issues in light and dark mode', function (): void {
    visit(route('styleguide.group', 'content'))->withLocale('it-IT')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();

    visit(route('styleguide.group', 'content'))->inDarkMode()->withLocale('it-IT')
        ->assertNoAccessibilityIssues();
});

it('keeps every control at least 44 px on mobile and desktop', function (): void {
    $components = '[data-styleguide-component]';

    visit(route('styleguide.group', 'content'))->withLocale('it-IT')
        ->assertScript(BrowserScripts::undersizedTargets($components), []);

    visit(route('styleguide.group', 'content'))->withLocale('it-IT')->on()->mobile()
        ->assertScript(BrowserScripts::undersizedTargets($components), [])
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertScript("document.querySelector('[data-demo=\"tabs\"] [data-tabs-scroller]').scrollWidth > document.querySelector('[data-demo=\"tabs\"] [data-tabs-scroller]').clientWidth", true);
});

it('moves between tabs with arrows, Home and End, selecting on focus', function (): void {
    $tab = static fn (string $name): string => '[data-demo="tabs"] [data-tab="'.$name.'"]';
    $panel = static fn (string $name): string => '#demo-animal-panel-'.$name;

    visit(route('styleguide.group', 'content'))->withLocale('it-IT')
        ->assertAttribute($tab('about'), 'aria-selected', 'true')
        ->assertAttribute($tab('about'), 'tabindex', '0')
        ->assertAttribute($tab('health'), 'tabindex', '-1')
        ->assertVisible($panel('about'))
        ->assertMissing($panel('health'))
        ->keys($tab('about'), ['ArrowRight'])
        ->assertScript(BrowserScripts::activeElement('data-tab'), 'health')
        ->assertAttribute($tab('health'), 'aria-selected', 'true')
        ->assertAttribute($tab('health'), 'tabindex', '0')
        ->assertAttribute($tab('about'), 'aria-selected', 'false')
        ->assertAttribute($tab('about'), 'tabindex', '-1')
        ->assertVisible($panel('health'))
        ->assertMissing($panel('about'))
        ->keys($tab('health'), ['End'])
        ->assertScript(BrowserScripts::activeElement('data-tab'), 'meet')
        ->assertAttribute($tab('meet'), 'aria-selected', 'true')
        ->keys($tab('meet'), ['ArrowRight'])
        ->assertScript(BrowserScripts::activeElement('data-tab'), 'about')
        ->keys($tab('about'), ['ArrowLeft'])
        ->assertScript(BrowserScripts::activeElement('data-tab'), 'meet')
        ->keys($tab('meet'), ['Home'])
        ->assertScript(BrowserScripts::activeElement('data-tab'), 'about')
        ->assertAttribute($tab('about'), 'aria-selected', 'true');
});

it('reaches the tab panel with Tab and selects tabs on click', function (): void {
    visit(route('styleguide.group', 'content'))->withLocale('it-IT')
        ->keys('[data-demo="tabs"] [data-tab="about"]', ['Tab'])
        ->assertScript(BrowserScripts::activeElement(), 'demo-animal-panel-about')
        ->click('[data-demo="tabs"] [data-tab="documents"]')
        ->assertAttribute('[data-demo="tabs"] [data-tab="documents"]', 'aria-selected', 'true')
        ->assertVisible('#demo-animal-panel-documents');
});

it('syncs the active tab with an outside model both ways', function (): void {
    $tab = static fn (string $name): string => '[data-demo="tabs-model"] [data-tab="'.$name.'"]';

    visit(route('styleguide.group', 'content'))->withLocale('it-IT')
        ->assertSeeIn('[data-demo="tabs-model-value"]', 'week')
        ->click($tab('month'))
        ->assertSeeIn('[data-demo="tabs-model-value"]', 'month')
        ->click('[data-demo="tabs-model-set"]')
        ->assertAttribute($tab('year'), 'aria-selected', 'true')
        ->assertAttribute($tab('month'), 'aria-selected', 'false')
        ->assertVisible('#demo-period-panel-year');
});

it('opens one accordion item at a time and moves focus with the arrows', function (): void {
    $trigger = static fn (string $name): string => '[data-demo="accordion-single"] [data-accordion-trigger="'.$name.'"]';

    visit(route('styleguide.group', 'content'))->withLocale('it-IT')
        ->assertAttribute($trigger('cost'), 'aria-expanded', 'true')
        ->assertVisible('#demo-faq-cost-panel')
        ->assertMissing('#demo-faq-time-panel')
        ->keys($trigger('time'), ['Enter'])
        ->assertAttribute($trigger('time'), 'aria-expanded', 'true')
        ->assertAttribute($trigger('cost'), 'aria-expanded', 'false')
        ->assertVisible('#demo-faq-time-panel')
        ->assertMissing('#demo-faq-cost-panel')
        ->keys($trigger('time'), ['Space'])
        ->assertAttribute($trigger('time'), 'aria-expanded', 'false')
        ->assertMissing('#demo-faq-time-panel')
        ->keys($trigger('cost'), ['ArrowDown'])
        ->assertScript(BrowserScripts::activeElement('data-accordion-trigger'), 'time')
        ->keys($trigger('cost'), ['ArrowUp'])
        ->assertScript(BrowserScripts::activeElement('data-accordion-trigger'), 'return')
        ->keys($trigger('time'), ['End'])
        ->assertScript(BrowserScripts::activeElement('data-accordion-trigger'), 'return')
        ->keys($trigger('return'), ['Home'])
        ->assertScript(BrowserScripts::activeElement('data-accordion-trigger'), 'cost')
        ->assertScript('getComputedStyle(document.activeElement).outlineWidth', '3px');
});

it('keeps several accordion items open in multiple mode', function (): void {
    $trigger = static fn (string $name): string => '[data-demo="accordion-multiple"] [data-accordion-trigger="'.$name.'"]';

    visit(route('styleguide.group', 'content'))->withLocale('it-IT')
        ->assertAttribute($trigger('species'), 'aria-expanded', 'true')
        ->assertAttribute($trigger('size'), 'aria-expanded', 'true')
        ->click($trigger('age'))
        ->assertAttribute($trigger('age'), 'aria-expanded', 'true')
        ->assertAttribute($trigger('species'), 'aria-expanded', 'true')
        ->assertVisible('#demo-filters-age-panel')
        ->assertVisible('#demo-filters-species-panel');
});

it('announces skeletons and hides their blocks from assistive technology', function (): void {
    visit(route('styleguide.group', 'content'))->withLocale('it-IT')
        ->assertAttribute('[data-demo="skeleton-rows"]', 'role', 'status')
        ->assertScript('document.querySelector(\'[data-demo="skeleton-rows"]\').hasAttribute(\'aria-busy\')', false)
        ->assertSeeIn('[data-demo="skeleton-rows"]', 'Caricamento delle candidature…')
        ->assertScript("[...document.querySelectorAll('[data-skeleton-block]')].every((block) => block.closest('[aria-hidden=\"true\"]') !== null)", true)
        ->assertScript("getComputedStyle(document.querySelector('[data-skeleton-block]')).animationName", 'pulse');
});

it('falls back to named initials when an avatar photo fails to load', function (): void {
    visit(route('styleguide.group', 'content'))->withLocale('it-IT')
        ->assertScript(<<<'JS'
            async () => {
                const image = document.querySelector('[data-demo="avatar-group"] img');
                const failed = new Promise((resolve) => image.addEventListener('error', resolve, { once: true }));
                image.loading = 'eager';
                image.src = 'data:image/png;base64,AAAA';
                await failed;
                await new Promise((resolve) => setTimeout(resolve, 50));
                const avatar = image.parentElement;
                return [image.hidden, avatar.getAttribute('role'), avatar.getAttribute('aria-label'), avatar.querySelector('[data-avatar-initials]').textContent];
            }
            JS, [true, 'img', 'Maria Rossi', 'MR'])
        ->assertSeeIn('[data-demo="avatar-group"] [data-avatar-more]', 'e altri 3');
});

it('keeps initials avatars distinct from the background they sit on, in light and dark mode', function (): void {
    // Per ogni avatar a iniziali: il suo fondo è diverso dal primo fondo opaco tra gli antenati
    // (in dark block-sun è navy-800 come bg-surface: il tono sun usa highlight).
    $blending = <<<'JS'
        [...document.querySelectorAll('[data-avatar]')]
            .filter((avatar) => !avatar.querySelector('img:not([hidden])') && avatar.checkVisibility())
            .filter((avatar) => {
                let parent = avatar.parentElement;
                while (parent && ['rgba(0, 0, 0, 0)', 'transparent'].includes(getComputedStyle(parent).backgroundColor)) {
                    parent = parent.parentElement;
                }
                return parent && getComputedStyle(parent).backgroundColor === getComputedStyle(avatar).backgroundColor;
            })
            .map((avatar) => avatar.getAttribute('aria-label') ?? avatar.textContent.trim())
        JS;

    visit(route('styleguide.group', 'content'))->withLocale('it-IT')
        ->assertScript('document.querySelectorAll(\'[data-avatar="sun"]\').length > 0', true)
        ->assertScript($blending, []);

    visit(route('styleguide.group', 'content'))->inDarkMode()->withLocale('it-IT')
        ->assertScript($blending, []);
});
