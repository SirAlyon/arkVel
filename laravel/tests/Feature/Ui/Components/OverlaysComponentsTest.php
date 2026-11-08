<?php

declare(strict_types=1);

/*
 | Componenti x-ui del gruppo "overlays": dialog, drawer, dropdown, tooltip, toast/toaster.
 | Test di rendering (markup, ARIA, varianti, traduzioni). Comportamento nel browser:
 | tests/Browser/Components/OverlaysComponentsTest.php.
 */

/** Le opzioni passate ad Alpine con @js, decodificate (JSON.parse('{\u0022…}')). */
function overlaysAlpineOptions(mixed $view): string
{
    return str_replace('\u0022', '"', html_entity_decode((string) $view));
}

describe('dialog', function (): void {
    it('renders a native modal dialog linked to its title and description', function (): void {
        $this->blade('<x-ui.dialog name="adopt" title="Vuoi conoscere Birba?" description="Ti ricontattiamo"><p>Corpo</p></x-ui.dialog>')
            ->assertSee('x-data="uiDialog(', escape: false)
            ->assertSee('x-modelable="isOpen"', escape: false)
            ->assertSee('x-on:open-dialog.window="targets($event) && show()"', escape: false)
            ->assertSee('<dialog', escape: false)
            ->assertSee('data-dialog="adopt"', escape: false)
            ->assertSee('x-bind:aria-labelledby="$id(\'ui-dialog-title\')"', escape: false)
            ->assertSee('x-bind:aria-describedby="$id(\'ui-dialog-description\')"', escape: false)
            ->assertSee('x-bind:id="$id(\'ui-dialog-title\')"', escape: false)
            ->assertSee('wire:ignore.self', escape: false)
            ->assertSee('rounded-panel bg-surface-raised text-fg shadow-overlay', escape: false)
            ->assertSee('w-lg', escape: false)
            ->assertSeeInOrder(['<h2', 'Vuoi conoscere Birba?', 'Ti ricontattiamo', 'Corpo'], escape: false)
            ->assertDontSee('role="alertdialog"', escape: false);
    });

    it('names the close button and leaves the description out when missing', function (): void {
        $this->blade('<x-ui.dialog title="Promemoria" />')
            ->assertSee('aria-label="Chiudi la finestra"', escape: false)
            ->assertSee('data-dialog-close', escape: false)
            ->assertSee('x-on:click="close()"', escape: false)
            ->assertDontSee('aria-describedby', escape: false)
            ->assertDontSee('data-dialog=', escape: false);
    });

    it('passes the dismissible option and the initial state to Alpine', function (): void {
        $html = (string) $this->blade('<x-ui.dialog name="info" title="Info" open />');

        expect(overlaysAlpineOptions($html))
            ->toContain('"name":"info"')
            ->toContain('"dismissible":true')
            ->toContain('"open":true');
    });

    it('renders a confirmation as an alertdialog that ignores clicks outside', function (): void {
        $html = (string) $this->blade('<x-ui.dialog name="delete" alert icon="trash-2" size="sm" title="Eliminare?" />');

        expect($html)
            ->toContain('role="alertdialog"')
            ->toContain('data-icon="trash-2"')
            ->toContain('bg-danger-subtle text-on-danger-subtle')
            ->toContain('w-md')
            ->and(overlaysAlpineOptions($html))->toContain('"dismissible":false');
    });

    it('puts wire:model on the Alpine wrapper and other attributes on the dialog', function (): void {
        $this->blade('<x-ui.dialog wire:model.live="showReminder" title="Promemoria" data-demo="reminder" class="max-w-none" />')
            ->assertSeeInOrder(['x-modelable="isOpen"', 'wire:model.live="showReminder"', '<dialog'], escape: false)
            ->assertSeeInOrder(['<dialog', 'max-w-none', 'data-demo="reminder"'], escape: false);
    });

    it('renders the trigger and the footer slots', function (): void {
        $this->blade(<<<'BLADE'
            <x-ui.dialog title="Domanda">
                <x-slot:trigger><x-ui.button>Apri</x-ui.button></x-slot:trigger>
                <x-slot:footer><x-ui.button variant="danger">Elimina</x-ui.button></x-slot:footer>
            </x-ui.dialog>
            BLADE)
            ->assertSeeInOrder(['data-dialog-trigger', 'Apri', '<dialog', 'data-dialog-footer', 'Elimina'], escape: false)
            ->assertSee('x-on:click="show()"', escape: false);
    });

    it('translates the close button', function (): void {
        app()->setLocale('en');

        $this->blade('<x-ui.dialog title="Reminder" />')->assertSee('aria-label="Close dialog"', escape: false);
    });

    it('carries its own toast outlet with live regions inside the dialog', function (): void {
        $this->blade('<x-ui.dialog title="Promemoria"><p>Corpo</p></x-ui.dialog>')
            ->assertSeeInOrder([
                '<dialog',
                'Corpo',
                'x-data="uiToasterOutlet"',
                'data-toaster-outlet="dialog"',
                'role="status"',
                'role="alert"',
                'popover="manual"',
                'x-for="toast in (speaks ? $store.toasts.items : [])"',
                '</dialog>',
            ], escape: false);
    });
});

describe('drawer', function (): void {
    it('renders a modal dialog anchored to the right by default', function (): void {
        $this->blade('<x-ui.drawer name="filters" title="Filtri"><p>Contenuto</p></x-ui.drawer>')
            ->assertSee('<dialog', escape: false)
            ->assertSee('data-drawer="filters"', escape: false)
            ->assertSee('data-side="right"', escape: false)
            ->assertSee('x-on:open-drawer.window="targets($event) && show()"', escape: false)
            ->assertSee('ml-auto rounded-l-panel', escape: false)
            ->assertSee('h-dvh max-h-none w-sm', escape: false)
            ->assertSee('aria-label="Chiudi il pannello"', escape: false)
            ->assertSee('data-drawer-body', escape: false)
            ->assertDontSee('data-sheet', escape: false);
    });

    it('anchors to each side', function (string $side, string $classes): void {
        $this->blade('<x-ui.drawer :side="$side" title="Menu" />', ['side' => $side])
            ->assertSee('data-side="'.$side.'"', escape: false)
            ->assertSee($classes, escape: false);
    })->with([
        ['left', 'mr-auto rounded-r-panel'],
        ['bottom', 'mt-auto w-full max-w-none rounded-t-panel'],
    ]);

    it('becomes a bottom sheet on mobile with sheet', function (): void {
        $this->blade('<x-ui.drawer sheet title="Filtri" />')
            ->assertSee('data-sheet', escape: false)
            ->assertSee('rounded-t-panel sm:mt-0 sm:h-dvh', escape: false)
            ->assertSee('sm:ml-auto sm:rounded-l-panel', escape: false);
    });

    it('falls back to the right side for unknown values', function (): void {
        $this->blade('<x-ui.drawer side="top" title="Menu" />')->assertSee('data-side="right"', escape: false);
    });

    it('renders a sticky footer and translates the close button', function (): void {
        app()->setLocale('en');

        $this->blade('<x-ui.drawer title="Filters"><x-slot:footer><x-ui.button>Apply</x-ui.button></x-slot:footer></x-ui.drawer>')
            ->assertSee('aria-label="Close panel"', escape: false)
            ->assertSee('sticky bottom-0', escape: false)
            ->assertSee('data-drawer-footer', escape: false);
    });

    it('marks the sticky header and footer, and carries a toast outlet', function (): void {
        $this->blade('<x-ui.drawer title="Filtri"><x-slot:footer><x-ui.button>Applica</x-ui.button></x-slot:footer></x-ui.drawer>')
            ->assertSeeInOrder(['<dialog', 'data-sticky="top"', 'data-sticky="bottom"', 'data-toaster-outlet', '</dialog>'], escape: false);
    });

    it('places its toasts away from the panel', function (string $side, string $placement, string $classes): void {
        $this->blade('<x-ui.drawer :side="$side" title="Menu" />', ['side' => $side])
            ->assertSee('data-toaster-outlet="'.$placement.'"', escape: false)
            ->assertSee($classes, escape: false);
    })->with([
        ['right', 'drawer-right', 'top-4 bottom-auto sm:top-auto sm:bottom-6 sm:left-6'],
        ['left', 'drawer-left', 'top-4 bottom-auto sm:top-auto sm:right-6 sm:bottom-6'],
        ['bottom', 'drawer-bottom', 'top-4 bottom-auto sm:top-6 sm:right-6'],
    ]);
});

describe('dropdown', function (): void {
    it('renders an APG menu button', function (): void {
        $this->blade('<x-ui.dropdown label="Azioni"><x-ui.dropdown.item icon="pencil">Modifica</x-ui.dropdown.item></x-ui.dropdown>')
            ->assertSee('x-data="uiDropdown"', escape: false)
            ->assertSee('aria-haspopup="menu"', escape: false)
            ->assertSee('aria-expanded="false"', escape: false)
            ->assertSee('x-bind:aria-expanded="open.toString()"', escape: false)
            ->assertSee('x-bind:aria-controls="$id(\'ui-dropdown-menu\')"', escape: false)
            ->assertSee('data-icon="chevron-down"', escape: false)
            ->assertSee('role="menu"', escape: false)
            ->assertSee('x-bind:aria-labelledby="$id(\'ui-dropdown-trigger\')"', escape: false)
            ->assertSee('popover="manual"', escape: false)
            ->assertSee('x-anchor.bottom-start.offset.8.fixed="$refs.trigger"', escape: false)
            ->assertSee('rounded-card bg-surface-raised p-2 text-fg shadow-overlay', escape: false)
            ->assertSeeInOrder(['data-dropdown-trigger', 'Azioni', 'role="menu"', 'role="menuitem"', 'Modifica'], escape: false);
    });

    it('aligns and positions the menu', function (): void {
        $this->blade('<x-ui.dropdown label="Altro" align="end" position="top" />')
            ->assertSee('x-anchor.top-end.offset.8.fixed', escape: false);
    });

    it('names an icon-only trigger with its label', function (): void {
        $this->blade('<x-ui.dropdown label="Altre azioni" icon="ellipsis-vertical" icon-only variant="ghost" />')
            ->assertSee('aria-label="Altre azioni"', escape: false)
            ->assertSee('data-icon="ellipsis-vertical"', escape: false)
            ->assertSee('data-variant="ghost"', escape: false)
            ->assertDontSee('data-icon="chevron-down"', escape: false);
    });

    it('renders button and link items outside the tab order', function (): void {
        $this->blade('<x-ui.dropdown.item wire:click="edit">Modifica</x-ui.dropdown.item>')
            ->assertSee('<button', escape: false)
            ->assertSee('type="button"', escape: false)
            ->assertSee('role="menuitem"', escape: false)
            ->assertSee('tabindex="-1"', escape: false)
            ->assertSee('min-h-11', escape: false)
            ->assertSee('wire:click="edit"', escape: false);

        $this->blade('<x-ui.dropdown.item href="/profilo" icon="user">Profilo</x-ui.dropdown.item>')
            ->assertSee('<a', escape: false)
            ->assertSee('href="/profilo"', escape: false)
            ->assertSee('data-icon="user"', escape: false)
            ->assertDontSee('type="button"', escape: false);
    });

    it('renders danger and disabled items', function (): void {
        $this->blade('<x-ui.dropdown.item variant="danger" icon="trash-2">Elimina</x-ui.dropdown.item>')
            ->assertSee('data-variant="danger"', escape: false)
            ->assertSee('text-danger hover:bg-danger-subtle', escape: false);

        $this->blade('<x-ui.dropdown.item href="/archivio" disabled>Archivia</x-ui.dropdown.item>')
            ->assertSee('aria-disabled="true"', escape: false)
            ->assertSee('text-fg-muted', escape: false)
            ->assertDontSee('href=', escape: false);
    });

    it('renders a decorative separator', function (): void {
        $this->blade('<x-ui.dropdown.separator />')
            ->assertSee('role="separator"', escape: false)
            ->assertSee('bg-line', escape: false);
    });
});

describe('tooltip', function (): void {
    it('renders a tooltip that describes its trigger', function (): void {
        $this->blade('<x-ui.tooltip text="Copia il link"><x-ui.button icon="share-2" label="Condividi" /></x-ui.tooltip>')
            ->assertSee('x-data="uiTooltip"', escape: false)
            ->assertSee('x-on:focusin="focus(true)"', escape: false)
            ->assertSee('x-on:mouseenter="pointer(true)"', escape: false)
            ->assertSee('x-on:keydown.escape="dismiss($event)"', escape: false)
            ->assertSee('role="tooltip"', escape: false)
            ->assertSee('popover="manual"', escape: false)
            ->assertSee('x-anchor.top.offset.8.fixed="trigger"', escape: false)
            ->assertSee('bg-inverse', escape: false)
            ->assertSee('text-on-inverse', escape: false)
            ->assertSeeInOrder(['aria-label="Condividi"', 'role="tooltip"', 'Copia il link'], escape: false);
    });

    it('accepts a position and falls back to top', function (): void {
        $this->blade('<x-ui.tooltip text="Sotto" position="bottom"><button>?</button></x-ui.tooltip>')
            ->assertSee('x-anchor.bottom.offset.8.fixed', escape: false);

        $this->blade('<x-ui.tooltip text="Boh" position="center"><button>?</button></x-ui.tooltip>')
            ->assertSee('x-anchor.top.offset.8.fixed', escape: false);
    });
});

describe('toast', function (): void {
    it('renders a static toast with icon, hidden type name and close button', function (string $type, string $icon, string $tone, string $label): void {
        $this->blade('<x-ui.toast :type="$type" title="Richiesta inviata" description="Ti rispondiamo presto" />', ['type' => $type])
            ->assertSee('data-type="'.$type.'"', escape: false)
            ->assertSee('data-icon="'.$icon.'"', escape: false)
            ->assertSee($tone, escape: false)
            ->assertSee('<span class="sr-only">'.$label.':</span>', escape: false)
            ->assertSeeInOrder(['Richiesta inviata', 'Ti rispondiamo presto'])
            ->assertSee('aria-label="Chiudi la notifica"', escape: false)
            ->assertSee('x-data="uiToast(', escape: false)
            ->assertSee('x-on:click="dismiss()"', escape: false)
            ->assertSee('rounded-card bg-surface-raised', escape: false)
            ->assertSee('shadow-overlay', escape: false);
    })->with([
        ['success', 'circle-check', 'bg-success-subtle text-on-success-subtle', 'Fatto'],
        ['warning', 'triangle-alert', 'bg-warning-subtle text-on-warning-subtle', 'Attenzione'],
        ['danger', 'circle-x', 'bg-danger-subtle text-on-danger-subtle', 'Errore'],
        ['info', 'info', 'bg-info-subtle text-on-info-subtle', 'Informazione'],
    ]);

    it('passes the focus target used when a static toast is dismissed', function (): void {
        expect(overlaysAlpineOptions($this->blade('<x-ui.toast title="Salvato" focus-target="#messages" />')))
            ->toContain('uiToast(JSON.parse(\'{"focusTarget":"#messages"}\'))');

        expect(overlaysAlpineOptions($this->blade('<x-ui.toast title="Salvato" />')))
            ->toContain('{"focusTarget":null}');
    });

    it('falls back to info for unknown types', function (): void {
        $this->blade('<x-ui.toast type="party" title="Ciao" />')
            ->assertSee('data-type="info"', escape: false)
            ->assertDontSee('data-icon="circle-check"', escape: false);
    });

    it('binds to the current store item without a title', function (): void {
        $this->blade('<x-ui.toast />')
            ->assertSee('x-bind:data-type="toast.type"', escape: false)
            ->assertSee('x-text="toast.title"', escape: false)
            ->assertSee('x-text="toast.description"', escape: false)
            ->assertSee('x-on:click="closeToast(toast.id, $event)"', escape: false)
            ->assertSeeInOrder(['data-toast-icon="success"', 'data-toast-icon="warning"', 'data-toast-icon="danger"', 'data-toast-icon="info"'], escape: false);
    });

    it('translates the type names and the close button', function (): void {
        app()->setLocale('en');

        $this->blade('<x-ui.toast type="danger" title="Payment failed" />')
            ->assertSee('<span class="sr-only">Error:</span>', escape: false)
            ->assertSee('aria-label="Dismiss notification"', escape: false);
    });
});

describe('toaster', function (): void {
    it('renders the live regions and the toast list', function (): void {
        $this->blade('<x-ui.toaster />')
            ->assertSee('x-data="uiToaster(', escape: false)
            ->assertSee('aria-label="Notifiche"', escape: false)
            ->assertSee('role="status"', escape: false)
            ->assertSee('role="alert"', escape: false)
            ->assertSee('x-text="speaks ? $store.toasts.polite : \'\'"', escape: false)
            ->assertSee('x-text="speaks ? $store.toasts.assertive : \'\'"', escape: false)
            ->assertSee('x-for="toast in (speaks ? $store.toasts.items : [])"', escape: false)
            ->assertSee('x-on:focusin="entered($event)"', escape: false)
            ->assertSee('fixed inset-x-0 bottom-0', escape: false)
            ->assertSee('sm:right-0', escape: false);
    });

    it('passes translated type names to the store', function (): void {
        app()->setLocale('en');

        expect(overlaysAlpineOptions($this->blade('<x-ui.toaster />')))
            ->toContain('"success":"Done"')
            ->toContain('"danger":"Error"')
            ->toContain('"flash":null')
            ->toContain('"announcement":":type. :title. :description"')
            ->toContain('"announcementShort":":type. :title"')
            ->toContain('aria-label="Notifications"');
    });

    it('reads a flashed toast from the session', function (): void {
        session()->flash('toast', ['type' => 'success', 'title' => 'Richiesta inviata']);

        expect(overlaysAlpineOptions($this->blade('<x-ui.toaster />')))
            ->toContain('"flash":{"type":"success","title":"Richiesta inviata"}');
    });

    it('turns a flashed string into a success toast', function (): void {
        session()->flash('toast', 'Salvato');

        expect(overlaysAlpineOptions($this->blade('<x-ui.toaster />')))
            ->toContain('"flash":{"type":"success","title":"Salvato"}');
    });
});

describe('styleguide page', function (): void {
    beforeEach(function (): void {
        app()->detectEnvironment(static fn (): string => 'local');
    });

    it('documents every overlay component', function (): void {
        $this->get(route('styleguide.group', 'overlays'))
            ->assertOk()
            ->assertSeeInOrder(['id="dialog"', 'id="drawer"', 'id="dropdown"', 'id="tooltip"', 'id="toast"'], escape: false)
            ->assertSee('data-toaster', escape: false)
            ->assertSee('Vuoi conoscere Birba?');
    });

    it('is translated into English', function (): void {
        $this->withSession(['locale' => 'en'])
            ->get(route('styleguide.group', 'overlays'))
            ->assertOk()
            ->assertSee('Would you like to meet Birba?')
            ->assertSee('Dismiss notification');
    });
});
