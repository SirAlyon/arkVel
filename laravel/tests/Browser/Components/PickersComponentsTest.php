<?php

declare(strict_types=1);

use Carbon\CarbonInterface;
use Tests\Support\Ui\BrowserScripts;

/*
 | Componenti x-ui del gruppo "pickers" nel browser: accessibilità (axe), tastiera di
 | combobox e date-picker, focus, live region, target ≥ 44 px, caricamento di Cally solo
 | dove serve. Pagina di prova: /_styleguide/pickers (solo local).
 | Gira SOLO nel servizio `browser-tests`: `docker compose --profile testing run --rm browser-tests`.
 |
 | Alpine, Cally (import dinamico) e gli annunci ritardati sono asincroni: `$eventually`
 | costruisce un'espressione che ripete il controllo finché il valore è quello atteso
 | (o finisce il tempo) e restituisce l'ultimo valore letto, da passare ad assertScript.
 */

$eventually = static function (string $expression, mixed $expected, int $timeout = 4000): string {
    $json = json_encode($expected, JSON_THROW_ON_ERROR);

    return <<<JS
        function() {
            return new Promise((resolve) => {
                const expected = JSON.stringify({$json});
                const started = Date.now();
                const read = () => { try { return ({$expression}); } catch (error) { return null; } };
                const tick = () => {
                    const value = read();
                    if (JSON.stringify(value) === expected || Date.now() - started > {$timeout}) {
                        resolve(value);
                    } else {
                        setTimeout(tick, 50);
                    }
                };
                tick();
            });
        }
        JS;
};

/** L'elemento con il focus, anche dentro lo shadow DOM di Cally. */
$deepActive = '(() => { let element = document.activeElement; while (element?.shadowRoot?.activeElement) { element = element.shadowRoot.activeElement; } return element; })()';

/** Opzioni visibili di una listbox. */
$visibleOptions = static fn (string $listbox): string => "[...document.querySelectorAll('#{$listbox} [role=option]')].filter((option) => option.checkVisibility()).map((option) => option.dataset.value)";

/** Valore di un elemento della pagina (es. l'input hidden). */
$valueOf = static fn (string $selector): string => 'document.querySelector('.json_encode($selector, JSON_THROW_ON_ERROR).')?.value ?? null';

beforeEach(function (): void {
    app()->detectEnvironment(static fn (): string => 'local');
});

it('has no accessibility issues in light and dark mode', function (): void {
    visit(route('styleguide.group', 'pickers'))->withLocale('it-IT')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();

    visit(route('styleguide.group', 'pickers'))->inDarkMode()->withLocale('it-IT')
        ->assertNoAccessibilityIssues();
});

it('has no accessibility issues with the popups open', function () use ($eventually, $deepActive): void {
    foreach ([false, true] as $dark) {
        $page = visit(route('styleguide.group', 'pickers'))->withLocale('it-IT');
        $page = $dark ? $page->inDarkMode() : $page;

        $page->keys('#field-breed', ['ArrowDown'])
            ->assertVisible('[data-demo="combobox-basic"] [data-combobox-popup]')
            ->assertNoAccessibilityIssues()
            ->keys('#field-breed', ['Escape'])
            ->keys('[data-demo="date-popover"] [data-date-picker-trigger]', ['Enter'])
            ->assertScript($eventually($deepActive.'?.getAttribute("part")?.includes("day") ?? false', true), true)
            ->assertNoAccessibilityIssues();
    }
});

it('keeps every control at least 44 px on mobile and desktop', function (): void {
    $components = '[data-styleguide-component]';

    visit(route('styleguide.group', 'pickers'))->withLocale('it-IT')
        ->assertScript(BrowserScripts::undersizedTargets($components), []);

    visit(route('styleguide.group', 'pickers'))->withLocale('it-IT')->on()->mobile()
        ->assertScript(BrowserScripts::undersizedTargets($components), [])
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
});

it('keeps the calendar days within the viewport and at least 24 px on mobile', function () use ($eventually, $deepActive): void {
    $days = "[...document.querySelector('[data-demo=\"date-popover\"] calendar-month').shadowRoot.querySelectorAll('button[part~=\"day\"]')].map((day) => day.getBoundingClientRect())";

    visit(route('styleguide.group', 'pickers'))->withLocale('it-IT')->on()->mobile()
        ->keys('[data-demo="date-popover"] [data-date-picker-trigger]', ['Enter'])
        ->assertScript($eventually($deepActive.'?.getAttribute("part")?.includes("day") ?? false', true), true)
        ->assertScript("{$days}.every((box) => box.width >= 24 && box.height >= 24 && box.left >= 0 && box.right <= window.innerWidth)", true)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
});

it('filters the combobox while typing and announces the results', function () use ($eventually, $visibleOptions): void {
    visit(route('styleguide.group', 'pickers'))->withLocale('it-IT')
        ->assertAttribute('#field-breed', 'aria-expanded', 'false')
        ->type('#field-breed', 'lab')
        ->assertAttribute('#field-breed', 'aria-expanded', 'true')
        ->assertScript($eventually($visibleOptions('field-breed-listbox'), ['labrador']), ['labrador'])
        ->assertScript($eventually("document.querySelector('[data-demo=\"combobox-basic\"] [data-combobox-status]').textContent", '1 risultato disponibile.'), '1 risultato disponibile.')
        ->type('#field-breed', 'EPAG')
        ->assertScript($eventually($visibleOptions('field-breed-listbox'), ['epagneul']), ['epagneul'])
        ->type('#field-breed', 'zzz')
        ->assertVisible('[data-demo="combobox-basic"] [data-combobox-empty]')
        ->assertScript($eventually("document.querySelector('[data-demo=\"combobox-basic\"] [data-combobox-status]').textContent", 'Nessun risultato.'), 'Nessun risultato.');
});

it('chooses an option from the keyboard', function () use ($eventually, $valueOf): void {
    visit(route('styleguide.group', 'pickers'))->withLocale('it-IT')
        ->type('#field-breed', 'lab')
        ->keys('#field-breed', ['ArrowDown'])
        ->assertScript($eventually("document.querySelector('#field-breed').getAttribute('aria-activedescendant')", 'field-breed-option-0'), 'field-breed-option-0')
        ->assertScript("document.querySelector('#field-breed-option-0').hasAttribute('data-active')", true)
        ->keys('#field-breed', ['Enter'])
        ->assertAttribute('#field-breed', 'aria-expanded', 'false')
        ->assertScript($eventually($valueOf('[data-demo="combobox-basic"] input[name="breed"]'), 'labrador'), 'labrador')
        ->assertValue('#field-breed', 'Labrador')
        ->assertAttribute('#field-breed-option-0', 'aria-selected', 'true')
        ->assertScript(BrowserScripts::activeElement(), 'field-breed');
});

it('moves through the options with arrows, Home and End and updates x-model', function () use ($eventually): void {
    $active = static fn (string $id) => $eventually("document.querySelector('#field-species').getAttribute('aria-activedescendant')", $id);

    visit(route('styleguide.group', 'pickers'))->withLocale('it-IT')
        ->assertValue('#field-species', 'Gatto')
        ->keys('#field-species', ['ArrowDown'])
        ->assertScript($active('field-species-option-1'), 'field-species-option-1')
        ->keys('#field-species', ['End'])
        ->assertScript($active('field-species-option-4'), 'field-species-option-4')
        ->keys('#field-species', ['ArrowDown'])
        ->assertScript($active('field-species-option-0'), 'field-species-option-0')
        ->keys('#field-species', ['ArrowUp'])
        ->assertScript($active('field-species-option-4'), 'field-species-option-4')
        ->keys('#field-species', ['Home'])
        ->assertScript($active('field-species-option-0'), 'field-species-option-0')
        ->keys('#field-species', ['Enter'])
        ->assertScript($eventually("document.querySelector('[data-demo=\"combobox-model\"] [data-demo-value]').textContent", 'dog'), 'dog');
});

it('closes with Esc and clears on the second Esc', function () use ($eventually, $valueOf): void {
    visit(route('styleguide.group', 'pickers'))->withLocale('it-IT')
        ->assertValue('#field-shelter', 'Canile Le Querce')
        ->keys('#field-shelter', ['ArrowDown'])
        ->assertAttribute('#field-shelter', 'aria-expanded', 'true')
        ->keys('#field-shelter', ['Escape'])
        ->assertAttribute('#field-shelter', 'aria-expanded', 'false')
        ->assertMissing('[data-demo="combobox-described"] [data-combobox-popup]')
        ->assertValue('#field-shelter', 'Canile Le Querce')
        ->keys('#field-shelter', ['Escape'])
        ->assertValue('#field-shelter', '')
        ->assertScript($eventually($valueOf('[data-demo="combobox-described"] input[name="shelter"]'), ''), '');
});

it('closes on Tab keeping the selection, and clears with the named button', function () use ($eventually): void {
    visit(route('styleguide.group', 'pickers'))->withLocale('it-IT')
        ->keys('#field-species', ['ArrowDown', 'ArrowDown'])
        ->keys('#field-species', ['Tab'])
        ->assertAttribute('#field-species', 'aria-expanded', 'false')
        ->assertScript(BrowserScripts::activeElement('data-combobox-clear'), '')
        ->assertValue('#field-species', 'Gatto')
        ->assertScript("document.querySelector('[data-demo=\"combobox-model\"] [data-demo-value]').textContent", 'cat')
        ->assertAttribute('[data-demo="combobox-model"] [data-combobox-clear]', 'aria-label', 'Svuota il campo')
        ->keys('[data-demo="combobox-model"] [data-combobox-clear]', ['Enter'])
        ->assertValue('#field-species', '')
        ->assertScript($eventually("document.querySelector('[data-demo=\"combobox-model\"] [data-demo-value]').textContent", 'nessuno'), 'nessuno')
        ->assertScript(BrowserScripts::activeElement(), 'field-species');
});

it('opens the date picker on the chosen day and selects from the keyboard', function () use ($eventually, $valueOf, $deepActive): void {
    $chosen = today()->addDays(7);
    $next = today()->addDays(8);
    $day = '[data-demo="date-popover"] calendar-month button[tabindex="0"]';

    visit(route('styleguide.group', 'pickers'))->withLocale('it-IT')
        ->assertValue('#field-visit-date', $chosen->locale('it')->isoFormat('LL'))
        ->keys('[data-demo="date-popover"] [data-date-picker-trigger]', ['Enter'])
        ->assertVisible('[data-demo="date-popover"] [data-date-picker-panel]')
        ->assertAttribute('[data-demo="date-popover"] [data-date-picker-trigger]', 'aria-expanded', 'true')
        ->assertScript($eventually($deepActive.'?.getAttribute("aria-label")', $chosen->locale('it')->isoFormat('D MMMM')), $chosen->locale('it')->isoFormat('D MMMM'))
        ->assertAttribute($day, 'aria-pressed', 'true')
        ->keys($day, ['ArrowRight'])
        ->assertScript($eventually($deepActive.'?.getAttribute("aria-label")', $next->locale('it')->isoFormat('D MMMM')), $next->locale('it')->isoFormat('D MMMM'))
        ->assertScript('getComputedStyle('.$deepActive.').outlineWidth', '3px')
        ->keys($day, ['Enter'])
        ->assertScript($eventually($valueOf('[data-demo="date-popover"] input[name="visit_date"]'), $next->format('Y-m-d')), $next->format('Y-m-d'))
        ->assertMissing('[data-demo="date-popover"] [data-date-picker-panel]')
        ->assertValue('#field-visit-date', $next->locale('it')->isoFormat('LL'))
        ->assertScript(BrowserScripts::activeElement('data-date-picker-trigger'), '')
        ->assertScript($eventually("document.querySelector('[data-demo=\"date-popover\"] [data-date-picker-status]').textContent", 'Data scelta: '.$next->locale('it')->isoFormat('LL')), 'Data scelta: '.$next->locale('it')->isoFormat('LL'));
});

it('closes the date picker with Esc or a click outside and returns focus to the button', function () use ($eventually, $deepActive): void {
    $trigger = '[data-demo="date-popover"] [data-date-picker-trigger]';
    $onDay = $eventually($deepActive.'?.getAttribute("part")?.includes("day") ?? false', true);

    visit(route('styleguide.group', 'pickers'))->withLocale('it-IT')
        ->keys($trigger, ['Enter'])
        ->assertScript($onDay, true)
        ->keys('[data-demo="date-popover"] calendar-month button[tabindex="0"]', ['Escape'])
        ->assertMissing('[data-demo="date-popover"] [data-date-picker-panel]')
        ->assertAttribute($trigger, 'aria-expanded', 'false')
        ->assertScript(BrowserScripts::activeElement('data-date-picker-trigger'), '')
        ->click($trigger)
        ->assertScript($onDay, true)
        ->click('#date-picker-title')
        ->assertMissing('[data-demo="date-popover"] [data-date-picker-panel]')
        ->assertScript(BrowserScripts::activeElement('data-date-picker-trigger'), '');
});

it('marks unavailable days and updates x-model, with a clear button', function () use ($eventually, $deepActive): void {
    $disallowed = "[...document.querySelector('[data-demo=\"date-disallowed\"] calendar-month').shadowRoot.querySelectorAll('button[aria-disabled=\"true\"]')].length > 0";
    $output = "document.querySelector('[data-demo=\"date-model\"] [data-demo-value]').textContent";
    $day = '[data-demo="date-model"] calendar-month button[tabindex="0"]';

    visit(route('styleguide.group', 'pickers'))->withLocale('it-IT')
        ->assertScript($eventually($disallowed, true), true)
        ->keys('[data-demo="date-model"] [data-date-picker-trigger]', ['Enter'])
        ->assertScript($eventually($deepActive.'?.getAttribute("aria-current")', 'date'), 'date')
        ->keys($day, ['ArrowLeft'])
        ->keys($day, ['Enter'])
        ->assertScript($eventually($output, today()->subDay()->format('Y-m-d')), today()->subDay()->format('Y-m-d'))
        ->assertScript($eventually("document.querySelector('[data-demo=\"date-model\"] [data-date-picker-clear]').checkVisibility()", true), true)
        ->keys('[data-demo="date-model"] [data-date-picker-clear]', ['Enter'])
        ->assertScript($eventually($output, 'nessuno'), 'nessuno')
        ->assertScript(BrowserScripts::activeElement('data-date-picker-input'), '');
});

it('moves the calendar and its heading when the value changes from outside', function () use ($eventually): void {
    // x-model della demo cambiato da fuori: Cally non emette focusday, il titolo deve seguire lo stesso.
    $date = today()->subMonthsNoOverflow(3)->day(15);
    $demo = 'document.querySelector(\'[data-demo="date-model"]\')';
    $setModel = static fn (string $value): string => "function() { Alpine.\$data({$demo}).birthDate = '{$value}'; return true; }";
    $heading = "{$demo}.querySelector('[data-date-picker-calendar] [slot=\"heading\"]').textContent";
    $focusedDay = "{$demo}.querySelector('calendar-month').shadowRoot.querySelector('button[tabindex=\"0\"]')?.getAttribute('aria-label')";
    $month = static fn (CarbonInterface $day): string => $day->locale('it')->isoFormat('MMMM YYYY');

    visit(route('styleguide.group', 'pickers'))->withLocale('it-IT')
        ->assertScript($eventually("customElements.get('calendar-date') !== undefined", true), true)
        ->assertScript($eventually($heading, $month(today())), $month(today()))
        ->assertScript($setModel($date->format('Y-m-d')), true)
        ->assertScript($eventually($heading, $month($date)), $month($date))
        ->assertScript($eventually($focusedDay, $date->locale('it')->isoFormat('D MMMM')), $date->locale('it')->isoFormat('D MMMM'))
        ->assertScript($setModel(''), true)
        ->assertScript($eventually($heading, $month(today())), $month(today()))
        ->assertScript($eventually($focusedDay, today()->locale('it')->isoFormat('D MMMM')), today()->locale('it')->isoFormat('D MMMM'));
});

it('selects a day in the inline calendar and writes it below', function () use ($eventually, $valueOf): void {
    // Le domeniche non sono disponibili: si va al primo giorno feriale dopo oggi.
    $steps = today()->addDay()->isSunday() ? 2 : 1;
    $date = today()->addDays($steps);
    $day = '[data-demo="date-inline"] calendar-month button[tabindex="0"]';

    visit(route('styleguide.group', 'pickers'))->withLocale('it-IT')
        ->assertSeeIn('[data-demo="date-inline"] [data-date-picker-status]', 'Nessuna data scelta')
        ->keys($day, array_fill(0, $steps, 'ArrowRight'))
        ->keys($day, ['Space'])
        ->assertScript($eventually($valueOf('[data-demo="date-inline"] input[name="slot_date"]'), $date->format('Y-m-d')), $date->format('Y-m-d'))
        ->assertSeeIn('[data-demo="date-inline"] [data-date-picker-status]', 'Data scelta: '.$date->locale('it')->isoFormat('LL'))
        ->assertScript($eventually("document.querySelector('[data-demo=\"date-inline\"] [data-date-picker-calendar] [slot=\"heading\"]').textContent", $date->locale('it')->isoFormat('MMMM YYYY')), $date->locale('it')->isoFormat('MMMM YYYY'));
});

it('loads Cally only on pages with a date picker', function () use ($eventually): void {
    $requested = "performance.getEntriesByType('resource').some((entry) => entry.name.includes('/cally-'))";

    visit(route('styleguide.group', 'actions'))->withLocale('it-IT')
        ->assertScript($requested, false)
        ->assertScript("customElements.get('calendar-date') === undefined", true);

    visit(route('styleguide.group', 'pickers'))->withLocale('it-IT')
        ->assertScript($eventually($requested, true), true)
        ->assertScript($eventually("customElements.get('calendar-date') !== undefined", true), true);
});
