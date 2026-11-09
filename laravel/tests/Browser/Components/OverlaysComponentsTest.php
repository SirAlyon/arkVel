<?php

declare(strict_types=1);

use Tests\Support\Ui\BrowserScripts;

/*
 | Componenti x-ui del gruppo "overlays" nel browser: accessibilità (axe, anche con dialog e
 | drawer aperti), tastiera, focus, target ≥ 44 px, toast sopra i modali. Pagina di prova:
 | /_styleguide/overlays. Niente attese fisse: le condizioni asincrone (transizioni, evento
 | `close`, timer dei toast) passano da overlaysEventually(), che ripete il controllo.
 | Gira SOLO nel servizio `browser-tests`: `docker compose --profile testing run --rm browser-tests`.
 */

beforeEach(function (): void {
    app()->detectEnvironment(static fn (): string => 'local');
});

/** Selettore JS di un elemento della pagina. */
function overlaysQuery(string $selector): string
{
    return 'document.querySelector('.json_encode($selector, JSON_THROW_ON_ERROR).')';
}

/** Stato `open` di un <dialog> (dialog o drawer) della pagina. */
function overlaysDialogIsOpen(string $selector): string
{
    return overlaysQuery($selector).'.open';
}

/** Il <dialog> è aperto e ha finito la transizione d'entrata. */
function overlaysDialogSettled(string $selector): string
{
    return '(() => { const dialog = '.overlaysQuery($selector).'; return dialog.open && dialog.getAnimations().length === 0; })()';
}

/**
 | Funzione JS che ripete `$condition` (un'espressione) ogni 25 ms finché è vera, per al
 | massimo `$timeout` ms, e restituisce l'ultimo esito: si usa con assertScript(…, true).
 */
function overlaysEventually(string $condition, int $timeout = 3000): string
{
    return 'function () { return new Promise((resolve) => { const end = Date.now() + '.$timeout.'; const check = () => { let ok = false; try { ok = Boolean('.$condition.'); } catch (error) { ok = false; } if (ok || Date.now() > end) { resolve(ok); } else { setTimeout(check, 25); } }; check(); }); }';
}

/** Lancia un toast con l'evento `toast` sulla finestra. */
function overlaysDispatchToast(array $detail): string
{
    return "function () { window.dispatchEvent(new CustomEvent('toast', { detail: ".json_encode($detail, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE).' })); return true; }';
}

/**
 * Lancia più toast, nell'ordine, in un solo script.
 *
 * @param  list<array<string, mixed>>  $toasts
 */
function overlaysDispatchToasts(array $toasts): string
{
    return 'function () { '.json_encode($toasts, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE).".forEach((detail) => window.dispatchEvent(new CustomEvent('toast', { detail }))); return true; }";
}

/** Il centro dell'elemento, al clic, colpisce proprio lui: è sopra a tutto e non è inerte. */
function overlaysOnTop(string $selector): string
{
    return '(() => { const element = '.overlaysQuery($selector).'; if (!element) { return false; } const box = element.getBoundingClientRect(); return element.contains(document.elementFromPoint(box.left + box.width / 2, box.top + box.height / 2)); })()';
}

it('has no accessibility issues in light and dark mode', function (): void {
    visit(route('styleguide.group', 'overlays'))->withLocale('it-IT')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();

    visit(route('styleguide.group', 'overlays'))->inDarkMode()->withLocale('it-IT')
        ->assertNoAccessibilityIssues();
});

it('has no accessibility issues with a dialog or a drawer open', function (string $trigger, string $dialog): void {
    visit(route('styleguide.group', 'overlays'))->withLocale('it-IT')
        ->click($trigger)
        ->assertScript(overlaysEventually(overlaysDialogSettled($dialog)), true)
        ->assertNoAccessibilityIssues();

    visit(route('styleguide.group', 'overlays'))->inDarkMode()->withLocale('it-IT')
        ->click($trigger)
        ->assertScript(overlaysEventually(overlaysDialogSettled($dialog)), true)
        ->assertNoAccessibilityIssues();
})->with([
    'dialog' => ['[data-demo="dialog-open"]', '[data-dialog="adoption-info"]'],
    'alert dialog' => ['[data-demo="confirm-open"]', '[data-dialog="delete-photo"]'],
    'drawer' => ['[data-demo="drawer-open"]', '[data-drawer="filters"]'],
]);

it('keeps every control at least 44 px on mobile and desktop', function (): void {
    $components = '[data-styleguide-component]';

    visit(route('styleguide.group', 'overlays'))->withLocale('it-IT')
        ->assertScript(BrowserScripts::undersizedTargets($components), []);

    visit(route('styleguide.group', 'overlays'))->withLocale('it-IT')->on()->mobile()
        ->assertScript(BrowserScripts::undersizedTargets($components), [])
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
});

it('opens a dialog from the keyboard, closes it with Esc and returns focus', function (): void {
    visit(route('styleguide.group', 'overlays'))->withLocale('it-IT')
        ->keys('[data-demo="dialog-open"]', ['Enter'])
        ->assertScript(overlaysDialogIsOpen('[data-dialog="adoption-info"]'), true)
        ->assertScript(BrowserScripts::activeElement('aria-label'), 'Chiudi la finestra')
        ->assertScript('document.documentElement.style.overflow', 'hidden')
        ->assertScript('document.getElementById(document.querySelector(\'[data-dialog="adoption-info"]\').getAttribute(\'aria-labelledby\')).textContent', 'Vuoi conoscere Birba?')
        ->keys(':focus', ['Escape'])
        ->assertScript(overlaysEventually("document.activeElement?.dataset.demo === 'dialog-open'"), true)
        ->assertScript(overlaysDialogIsOpen('[data-dialog="adoption-info"]'), false)
        ->assertScript('document.documentElement.style.overflow', '');
});

it('closes a dialog from a footer action', function (): void {
    visit(route('styleguide.group', 'overlays'))->withLocale('it-IT')
        ->keys('[data-demo="dialog-open"]', ['Enter'])
        ->keys('[data-demo="dialog-cancel"]', ['Enter'])
        ->assertScript(overlaysEventually("document.activeElement?.dataset.demo === 'dialog-open'"), true)
        ->assertScript(overlaysDialogIsOpen('[data-dialog="adoption-info"]'), false);
});

it('closes a dialog on a backdrop click, but not a confirmation', function (): void {
    $backdropClick = static fn (string $selector): string => 'const dialog = '.overlaysQuery($selector).'; '
        ."['pointerdown', 'click'].forEach((type) => dialog.dispatchEvent(new MouseEvent(type, { bubbles: true, clientX: 2, clientY: 2 }))); return true;";

    visit(route('styleguide.group', 'overlays'))->withLocale('it-IT')
        ->click('[data-demo="dialog-open"]')
        ->assertScript('function () { '.$backdropClick('[data-dialog="adoption-info"]').' }', true)
        ->assertScript(overlaysEventually("document.activeElement?.dataset.demo === 'dialog-open'"), true)
        ->assertScript(overlaysDialogIsOpen('[data-dialog="adoption-info"]'), false)
        ->keys('[data-demo="confirm-open"]', ['Enter'])
        ->assertAttribute('[data-dialog="delete-photo"]', 'role', 'alertdialog')
        ->assertScript(BrowserScripts::activeElement('data-demo'), 'confirm-cancel')
        ->assertScript('function () { '.$backdropClick('[data-dialog="delete-photo"]').' }', true)
        ->assertScript(overlaysDialogIsOpen('[data-dialog="delete-photo"]'), true)
        ->assertScript(BrowserScripts::activeElement('data-demo'), 'confirm-cancel')
        ->keys(':focus', ['Escape'])
        ->assertScript(overlaysEventually("document.activeElement?.dataset.demo === 'confirm-open'"), true)
        ->assertScript(overlaysDialogIsOpen('[data-dialog="delete-photo"]'), false);
});

it('keeps a bound boolean in sync with the dialog', function (): void {
    visit(route('styleguide.group', 'overlays'))->withLocale('it-IT')
        ->assertSeeIn('[data-demo="model-state"]', 'chiuso')
        ->keys('[data-demo="model-open"]', ['Enter'])
        ->assertScript(overlaysDialogIsOpen('[data-demo="model-dialog"]'), true)
        ->assertSeeIn('[data-demo="model-state"]', 'aperto')
        ->keys(':focus', ['Escape'])
        ->assertScript(overlaysEventually("document.activeElement?.dataset.demo === 'model-open'"), true)
        ->assertSeeIn('[data-demo="model-state"]', 'chiuso');
});

it('opens a drawer from the keyboard and returns focus on Esc', function (): void {
    visit(route('styleguide.group', 'overlays'))->withLocale('it-IT')
        ->keys('[data-demo="menu-open"]', ['Enter'])
        ->assertScript(overlaysEventually(overlaysDialogSettled('[data-drawer="menu"]')), true)
        ->assertScript(overlaysQuery('[data-drawer="menu"]').'.getBoundingClientRect().left', 0)
        ->assertScript(BrowserScripts::activeElement('aria-label'), 'Chiudi il pannello')
        ->keys(':focus', ['Escape'])
        ->assertScript(overlaysEventually("document.activeElement?.getAttribute('aria-label') === 'Apri il menu'"), true)
        ->assertScript(overlaysDialogIsOpen('[data-drawer="menu"]'), false);
});

it('turns the filters drawer into a bottom sheet on mobile', function (): void {
    visit(route('styleguide.group', 'overlays'))->withLocale('it-IT')->on()->mobile()
        ->click('[data-demo="drawer-open"]')
        ->assertScript(overlaysEventually(overlaysDialogSettled('[data-drawer="filters"]')), true)
        ->assertScript('(() => { const box = document.querySelector(\'[data-drawer="filters"]\').getBoundingClientRect(); return Math.round(box.bottom) === window.innerHeight && Math.round(box.width) === document.documentElement.clientWidth; })()', true)
        ->keys('[data-demo="drawer-apply"]', ['Enter'])
        ->assertScript(overlaysEventually("document.activeElement?.dataset.demo === 'drawer-open'"), true)
        ->assertScript(overlaysDialogIsOpen('[data-drawer="filters"]'), false);
});

it('keeps the focused control clear of the sticky drawer header and footer', function (): void {
    $drawer = '[data-drawer="filters"]';
    $clear = '(() => { const focused = document.activeElement.getBoundingClientRect(); '
        .'const header = '.overlaysQuery($drawer.' [data-sticky="top"]').'.getBoundingClientRect(); '
        .'const footer = '.overlaysQuery($drawer.' [data-sticky="bottom"]').'.getBoundingClientRect(); '
        .'return focused.top >= header.bottom && focused.bottom <= footer.top; })()';

    $page = visit(route('styleguide.group', 'overlays'))->withLocale('it-IT')
        ->resize(1024, 520)
        ->click('[data-demo="drawer-open"]')
        ->assertScript(overlaysEventually(overlaysDialogSettled($drawer)), true)
        ->assertScript('(() => { const drawer = '.overlaysQuery($drawer).'; return drawer.scrollHeight > drawer.clientHeight; })()', true)
        ->assertScript(BrowserScripts::activeElement('aria-label'), 'Chiudi il pannello');

    // Dal bottone di chiusura, Tab su tutte le 13 opzioni dei filtri (3 + 3 + 4 + 3) fino all'ultima.
    $page->keys(':focus', array_fill(0, 13, 'Tab'))
        ->assertScript(BrowserScripts::activeElement('data-demo'), 'drawer-last')
        ->assertScript(overlaysEventually($clear), true)
        ->assertScript(overlaysQuery($drawer).'.scrollTop > 0', true)
        // E tornando indietro, con Maiusc+Tab, la prima opzione esce da sotto l'intestazione.
        ->keys(':focus', array_fill(0, 12, 'Shift+Tab'))
        ->assertScript("document.activeElement?.textContent.trim() === 'Cani'", true)
        ->assertScript(overlaysEventually($clear), true);
});

it('navigates a dropdown menu with the keyboard', function (): void {
    $trigger = '[data-demo="dropdown-actions"] [data-dropdown-trigger]';

    visit(route('styleguide.group', 'overlays'))->withLocale('it-IT')
        ->assertAttribute($trigger, 'aria-haspopup', 'menu')
        ->assertAttribute($trigger, 'aria-expanded', 'false')
        ->keys($trigger, ['ArrowDown'])
        ->assertAttribute($trigger, 'aria-expanded', 'true')
        ->assertVisible('[data-demo="dropdown-actions"] [role="menu"]')
        ->assertScript(BrowserScripts::activeElement('data-item'), 'edit')
        ->keys(':focus', ['ArrowUp'])
        ->assertScript(BrowserScripts::activeElement('data-item'), 'delete')
        ->keys(':focus', ['ArrowDown'])
        ->assertScript(BrowserScripts::activeElement('data-item'), 'edit')
        ->keys(':focus', ['End'])
        ->assertScript(BrowserScripts::activeElement('data-item'), 'delete')
        ->keys(':focus', ['Home', 'ArrowDown', 'ArrowDown', 'ArrowDown'])
        ->assertScript(BrowserScripts::activeElement('data-item'), 'archive')
        ->keys(':focus', ['Enter'])
        ->assertSeeIn('[data-demo="dropdown-choice"]', 'nessuna')
        ->assertAttribute($trigger, 'aria-expanded', 'true')
        ->keys(':focus', ['d'])
        ->assertScript(BrowserScripts::activeElement('data-item'), 'duplicate')
        ->keys(':focus', ['Enter'])
        ->assertSeeIn('[data-demo="dropdown-choice"]', 'Duplica')
        ->assertAttribute($trigger, 'aria-expanded', 'false')
        ->assertMissing('[data-demo="dropdown-actions"] [role="menu"]')
        ->assertScript(BrowserScripts::activeElement('aria-haspopup'), 'menu');
});

it('closes a dropdown with Esc and Tab', function (): void {
    $trigger = '[data-demo="dropdown-actions"] [data-dropdown-trigger]';

    visit(route('styleguide.group', 'overlays'))->withLocale('it-IT')
        ->keys($trigger, ['Enter'])
        ->assertScript(BrowserScripts::activeElement('data-item'), 'edit')
        ->keys(':focus', ['Escape'])
        ->assertAttribute($trigger, 'aria-expanded', 'false')
        ->assertScript(BrowserScripts::activeElement('aria-haspopup'), 'menu')
        ->keys($trigger, ['ArrowUp'])
        ->assertScript(BrowserScripts::activeElement('data-item'), 'delete')
        ->keys(':focus', ['Tab'])
        ->assertAttribute($trigger, 'aria-expanded', 'false')
        ->assertMissing('[data-demo="dropdown-actions"] [role="menu"]');
});

it('shows a tooltip on focus and hides it with Esc without moving focus', function (): void {
    $trigger = '[data-demo="tooltip-share"] button';
    $tooltip = '[data-demo="tooltip-share"] [role="tooltip"]';

    visit(route('styleguide.group', 'overlays'))->withLocale('it-IT')
        ->assertMissing($tooltip)
        ->keys($trigger, ['Shift'])
        ->assertScript(overlaysEventually(overlaysQuery($tooltip).".matches(':popover-open')"), true)
        ->assertVisible($tooltip)
        ->assertSeeIn($tooltip, 'Copia il link della scheda')
        ->assertScript('document.querySelector(\'[data-demo="tooltip-share"] button\').getAttribute(\'aria-describedby\') === document.querySelector(\'[data-demo="tooltip-share"] [role="tooltip"]\').id', true)
        ->keys(':focus', ['Escape'])
        ->assertScript(overlaysEventually('!'.overlaysQuery($tooltip).".matches(':popover-open')"), true)
        ->assertMissing($tooltip)
        ->assertScript(BrowserScripts::activeElement('aria-label'), 'Condividi');
});

it('keeps a tooltip open while the pointer moves onto it', function (): void {
    $tooltip = '[data-demo="tooltip-share"] [role="tooltip"]';
    $open = overlaysQuery($tooltip).".matches(':popover-open')";

    visit(route('styleguide.group', 'overlays'))->withLocale('it-IT')
        ->hover('[data-demo="tooltip-share"] button')
        ->assertScript(overlaysEventually($open), true)
        ->hover($tooltip)
        // Oltre il ritardo di uscita (100 ms) il tooltip è ancora aperto.
        ->assertScript('function () { return new Promise((resolve) => setTimeout(() => resolve('.$open.'), 300)); }', true)
        ->hover('#tooltip-title')
        ->assertScript(overlaysEventually('!'.$open), true)
        ->assertMissing($tooltip);
});

it('announces toasts and lets you dismiss them', function (): void {
    visit(route('styleguide.group', 'overlays'))->withLocale('it-IT')
        ->assertCount('[data-toaster] [data-toast]', 0)
        ->keys('[data-toast-demo="success"]', ['Enter'])
        ->assertScript(overlaysEventually("document.querySelector('[data-toaster] [data-toaster-status]').textContent.includes('Fatto. Richiesta inviata.')"), true)
        ->assertCount('[data-toaster] [data-toast]', 1)
        ->assertSeeIn('[data-toaster] [data-toast]', 'Richiesta inviata')
        ->keys('[data-toast-demo="danger"]', ['Enter'])
        ->assertScript(overlaysEventually("document.querySelector('[data-toaster] [data-toaster-alert]').textContent.includes('Errore. Pagamento non riuscito.')"), true)
        ->assertCount('[data-toaster] [data-toast]', 2)
        ->assertAttribute('[data-toaster] [data-toast][data-type="danger"] [data-toast-close]', 'aria-label', 'Chiudi la notifica')
        ->keys('[data-toaster] [data-toast][data-type="danger"] [data-toast-close]', ['Enter'])
        ->assertScript(overlaysEventually("document.querySelectorAll('[data-toaster] [data-toast]').length === 1"), true)
        ->assertScript(BrowserScripts::activeElement('aria-label'), 'Chiudi la notifica');
});

it('fires toasts from a window event and closes them automatically', function (): void {
    visit(route('styleguide.group', 'overlays'))->withLocale('it-IT')
        ->assertScript(overlaysDispatchToast(['type' => 'info', 'title' => 'Ciao', 'duration' => 600]), true)
        ->assertScript(overlaysEventually("document.querySelector('[data-toaster] [data-toast][data-type=\"info\"]')?.textContent.includes('Ciao')"), true)
        ->assertScript(overlaysEventually("document.querySelectorAll('[data-toaster] [data-toast]').length === 0"), true)
        ->assertNoJavaScriptErrors();
});

it('shows, announces and dismisses toasts above an open dialog', function (): void {
    $dialog = '[data-dialog="adoption-info"]';

    visit(route('styleguide.group', 'overlays'))->withLocale('it-IT')
        ->keys('[data-demo="dialog-open"]', ['Enter'])
        ->assertScript(overlaysEventually(overlaysDialogSettled($dialog)), true)
        ->assertScript(overlaysDispatchToast(['type' => 'danger', 'title' => 'Pagamento non riuscito', 'description' => 'Riprova.']), true)
        // L'annuncio parte dalla regione role="alert" dentro il dialog, non da quella (inerte) della pagina.
        ->assertScript(overlaysEventually(overlaysQuery($dialog.' [data-toaster-alert]').".textContent === 'Errore. Pagamento non riuscito. Riprova.'"), true)
        ->assertAttribute($dialog.' [data-toaster-alert]', 'role', 'alert')
        ->assertScript(overlaysQuery('[data-toaster] [data-toaster-alert]').'.textContent', '')
        ->assertCount('[data-toaster] [data-toast]', 0)
        // Il toast sta sopra dialog e backdrop: il suo bottone di chiusura riceve i clic.
        ->assertScript(overlaysEventually(overlaysOnTop($dialog.' [data-toast] [data-toast-close]')), true)
        // Ed è raggiungibile con Tab dopo le azioni del dialog (chiudi, Annulla, Invia).
        ->assertScript(BrowserScripts::activeElement('aria-label'), 'Chiudi la finestra')
        ->keys(':focus', ['Tab', 'Tab', 'Tab'])
        ->assertScript('document.activeElement?.closest(\'[data-toast]\')?.dataset.type', 'danger')
        ->assertScript(BrowserScripts::activeElement('aria-label'), 'Chiudi la notifica')
        ->keys(':focus', ['Enter'])
        ->assertScript(overlaysEventually("document.querySelectorAll('".$dialog." [data-toast]').length === 0"), true)
        ->assertScript(overlaysDialogIsOpen($dialog), true)
        ->assertScript(overlaysQuery($dialog).'.contains(document.activeElement)', true)
        // Un toast di conferma: regione role="status" del dialog.
        ->assertScript(overlaysDispatchToast(['type' => 'success', 'title' => 'Richiesta inviata']), true)
        ->assertScript(overlaysEventually(overlaysQuery($dialog.' [data-toaster-status]').".textContent === 'Fatto. Richiesta inviata'"), true)
        ->assertAttribute($dialog.' [data-toaster-status]', 'role', 'status')
        ->assertScript(overlaysEventually(overlaysOnTop($dialog.' [data-toast][data-type="success"] [data-toast-close]')), true)
        ->assertScript('function () { const button = '.overlaysQuery($dialog.' [data-toast] [data-toast-close]').'; button.focus(); return document.activeElement === button; }', true)
        // Chiuso il dialog (Esc da un suo controllo: sul toast Esc chiuderebbe il toast), il
        // toast ancora attivo torna nel toaster della pagina.
        ->keys($dialog.' [data-demo="dialog-cancel"]', ['Escape'])
        ->assertScript(overlaysEventually("document.activeElement?.dataset.demo === 'dialog-open'"), true)
        ->assertScript(overlaysEventually("document.querySelectorAll('[data-toaster] [data-toast]').length === 1"), true)
        ->assertScript(overlaysQuery($dialog.' [data-toaster-status]').'.textContent', '');
});

it('shows a toast fired from inside a drawer above it', function (): void {
    $drawer = '[data-drawer="filters"]';

    visit(route('styleguide.group', 'overlays'))->withLocale('it-IT')
        ->click('[data-demo="drawer-open"]')
        ->assertScript(overlaysEventually(overlaysDialogSettled($drawer)), true)
        ->keys('[data-demo="drawer-reset"]', ['Enter'])
        ->assertScript(overlaysEventually(overlaysQuery($drawer.' [data-toaster-status]').".textContent === 'Informazione. Filtri azzerati'"), true)
        ->assertScript(overlaysEventually(overlaysOnTop($drawer.' [data-toast][data-type="info"] [data-toast-close]')), true)
        ->assertScript(BrowserScripts::activeElement('data-demo'), 'drawer-reset')
        // Il toast non copre il pannello (e le azioni del suo footer): sta dal lato opposto.
        ->assertScript('(() => { const toast = '.overlaysQuery($drawer.' [data-toast]').'.getBoundingClientRect(); const panel = '.overlaysQuery($drawer).'.getBoundingClientRect(); return toast.right <= panel.left; })()', true)
        ->assertScript(overlaysOnTop($drawer.' [data-demo="drawer-apply"]'), true);
});

it('moves focus to the next control when a static toast is dismissed', function (): void {
    $close = static fn (string $type): string => '#toast [data-toast][data-type="'.$type.'"] [data-toast-close]';

    visit(route('styleguide.group', 'overlays'))->withLocale('it-IT')
        ->keys($close('success'), ['Enter'])
        ->assertScript(overlaysEventually('!'.overlaysQuery('#toast [data-toast][data-type="success"]').'.checkVisibility()'), true)
        ->assertScript('document.activeElement?.closest(\'[data-toast]\')?.dataset.type', 'warning')
        ->assertScript(BrowserScripts::activeElement('aria-label'), 'Chiudi la notifica')
        ->keys($close('info'), ['Enter'])
        ->assertScript(overlaysEventually('!'.overlaysQuery('#toast [data-toast][data-type="info"]').'.checkVisibility()'), true)
        ->assertScript('document.activeElement !== document.body && document.activeElement.checkVisibility()', true)
        ->assertScript('document.activeElement.closest(\'[data-toast]\') === null', true);
});

/**
 | Prepara il caso WCAG 2.4.11 con la pila dei toast aperta: sceglie un elemento
 | focalizzabile `cur` della pagina (a metà, o l'ultimo prima del toaster con `$last`) e
 | quello che lo precede, `prev`; fa scorrere la pagina perché `cur` finisca sotto la pila
 | (a metà) o in fondo alla pagina, mette il focus su `prev` e marca `cur` con
 | data-under-toasts. Restituisce true se `cur` è davvero coperto e `prev` no: il Tab
 | successivo porta il focus su un elemento sotto i toast.
 */
function overlaysPlaceUnderToasts(bool $last = false): string
{
    $mode = $last ? 'true' : 'false';

    return <<<JS
        function () {
            const list = document.querySelector('[data-toaster] [data-toaster-list]');
            const covers = (element) => { const area = list.getBoundingClientRect(); const box = element.getBoundingClientRect(); return area.height > 0 && box.bottom > area.top && box.top < area.bottom && box.right > area.left && box.left < area.right; };
            const focusables = [...document.querySelectorAll('a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select, textarea, summary, [tabindex="0"]')]
                .filter((element) => !element.closest('[data-toaster], dialog, [inert]') && element.getAttribute('tabindex') !== '-1' && element.checkVisibility() && element.getBoundingClientRect().height > 0);
            const scrollBy = (top) => window.scrollTo({ top: window.scrollY + top, behavior: 'instant' });
            let index;
            if ({$mode}) {
                index = focusables.length - 1;
                window.scrollTo({ top: document.documentElement.scrollHeight, behavior: 'instant' });
            } else {
                index = focusables.findIndex((element, position) => position > 0 && element.getBoundingClientRect().top + window.scrollY > window.innerHeight
                    && element.getBoundingClientRect().top - focusables[position - 1].getBoundingClientRect().bottom > 16);
                scrollBy(focusables[index].getBoundingClientRect().top - (list.getBoundingClientRect().top + 4));
            }
            const cur = focusables[index];
            const prev = focusables[index - 1];
            document.querySelectorAll('[data-under-toasts]').forEach((element) => element.removeAttribute('data-under-toasts'));
            cur.setAttribute('data-under-toasts', '');
            prev.focus({ preventScroll: true });
            return document.activeElement === prev && covers(cur) && !covers(prev);
        }
        JS;
}

/** L'elemento con il focus è quello marcato e non interseca la pila dei toast. */
function overlaysFocusUncovered(): string
{
    return "(() => { const target = document.activeElement; if (!target?.hasAttribute('data-under-toasts')) { return false; } const area = document.querySelector('[data-toaster] [data-toaster-list]').getBoundingClientRect(); const box = target.getBoundingClientRect(); return area.height > 0 && !(box.bottom > area.top && box.top < area.bottom && box.right > area.left && box.left < area.right); })()";
}

it('keeps the element with keyboard focus out from under the toasts on mobile', function (bool $last): void {
    $toasts = array_map(
        static fn (int $number): array => ['type' => 'warning', 'title' => 'Promemoria '.$number, 'description' => 'Controlla i documenti della richiesta di adozione.', 'duration' => 0],
        range(1, 4),
    );

    visit(route('styleguide.group', 'overlays'))->withLocale('it-IT')->on()->mobile()
        ->assertScript(overlaysDispatchToasts($toasts), true)
        // Sotto i 640 px restano al massimo 2 toast: i più vecchi si chiudono.
        ->assertScript(overlaysEventually("document.querySelectorAll('[data-toaster] [data-toast]').length === 2"), true)
        ->assertDontSeeIn('[data-toaster]', 'Promemoria 1')
        ->assertDontSeeIn('[data-toaster]', 'Promemoria 2')
        ->assertScript(overlaysPlaceUnderToasts($last), true)
        ->keys(':focus', ['Tab'])
        ->assertScript(overlaysEventually(overlaysFocusUncovered()), true);
})->with([
    'in the middle of the page' => [false],
    'at the end of the page' => [true],
]);

it('keeps up to 5 toasts stacked on wide screens', function (): void {
    $toasts = array_map(static fn (int $number): array => ['type' => 'info', 'title' => 'Notifica '.$number, 'duration' => 0], range(1, 6));

    visit(route('styleguide.group', 'overlays'))->withLocale('it-IT')
        ->assertScript(overlaysDispatchToasts($toasts), true)
        ->assertScript(overlaysEventually("document.querySelectorAll('[data-toaster] [data-toast]').length === 5"), true)
        ->assertDontSeeIn('[data-toaster]', 'Notifica 1');
});

it('closes the focused toast with Escape, except errors', function (): void {
    visit(route('styleguide.group', 'overlays'))->withLocale('it-IT')
        ->assertScript(overlaysDispatchToast(['type' => 'danger', 'title' => 'Pagamento non riuscito', 'duration' => 0]), true)
        ->assertScript(overlaysDispatchToast(['type' => 'success', 'title' => 'Richiesta inviata', 'duration' => 0]), true)
        ->assertScript(overlaysEventually("document.querySelectorAll('[data-toaster] [data-toast]').length === 2"), true)
        ->keys('[data-toaster] [data-toast][data-type="success"] [data-toast-close]', ['Escape'])
        ->assertScript(overlaysEventually("document.querySelectorAll('[data-toaster] [data-toast]').length === 1"), true)
        // Il focus passa al toast rimasto, non si perde.
        ->assertScript('document.activeElement?.closest(\'[data-toast]\')?.dataset.type', 'danger')
        ->keys(':focus', ['Escape'])
        ->assertScript('function () { return new Promise((resolve) => setTimeout(() => resolve(document.querySelectorAll(\'[data-toaster] [data-toast]\').length), 400)); }', 1);
});

it('closes a toast above a dialog with Escape and leaves the dialog open', function (): void {
    $dialog = '[data-dialog="adoption-info"]';

    visit(route('styleguide.group', 'overlays'))->withLocale('it-IT')
        ->keys('[data-demo="dialog-open"]', ['Enter'])
        ->assertScript(overlaysEventually(overlaysDialogSettled($dialog)), true)
        ->assertScript(overlaysDispatchToast(['type' => 'success', 'title' => 'Richiesta inviata', 'duration' => 0]), true)
        ->assertScript(overlaysEventually(overlaysOnTop($dialog.' [data-toast] [data-toast-close]')), true)
        ->keys($dialog.' [data-toast] [data-toast-close]', ['Escape'])
        ->assertScript(overlaysEventually("document.querySelectorAll('".$dialog." [data-toast]').length === 0"), true)
        ->assertScript(overlaysDialogIsOpen($dialog), true);
});

it('keeps the drawer footer buttons on a single line of text', function (string $device): void {
    $drawer = '[data-drawer="filters"]';
    // Ogni bottone del footer è alto quanto il suo min-height (una riga di testo) e sta nel footer.
    $tidy = '(() => { const footer = '.overlaysQuery($drawer.' [data-drawer-footer]').'.getBoundingClientRect(); return [...'.overlaysQuery($drawer.' [data-drawer-footer]').'.children].every((button) => { const box = button.getBoundingClientRect(); return box.height <= parseFloat(getComputedStyle(button).minHeight) + 1 && box.left >= footer.left && box.right <= footer.right; }); })()';

    visit(route('styleguide.group', 'overlays'))->withLocale('it-IT')->on()->{$device}()
        ->click('[data-demo="drawer-open"]')
        ->assertScript(overlaysEventually(overlaysDialogSettled($drawer)), true)
        ->assertScript($tidy, true)
        ->assertScript(overlaysOnTop($drawer.' [data-demo="drawer-apply"]'), true);
})->with(['desktop', 'mobile', 'iPhoneSE']);
