/*
 | x-ui · gruppo "contenuti": dati Alpine di tabs e accordion
 | (resources/views/components/ui/{tabs,accordion}.blade.php).
 |
 | Lo stato iniziale arriva sempre dal render del server (aria-selected, aria-expanded,
 | `hidden`): qui si leggono quei valori e si gestiscono click e tastiera secondo i
 | pattern WAI-ARIA APG. Gli elementi di un componente annidato dentro un altro dello
 | stesso tipo appartengono al più vicino (owns()).
 */

/** Gli elementi `selector` di questo componente, esclusi quelli dei componenti annidati. */
const owns = (root, selector, rootSelector) =>
    [...root.querySelectorAll(selector)].filter((element) => element.closest(rootSelector) === root);

/** Indice di destinazione per i tasti di navigazione (circolare), o null se il tasto non serve. */
const targetIndex = (key, index, length, previous, next) => {
    const targets = { [previous]: index - 1, [next]: index + 1, Home: 0, End: length - 1 };

    return key in targets ? (targets[key] + length) % length : null;
};

/**
 | Porta `tab` in vista nella sua lista scorrevole ([data-tabs-scroller]), con un margine
 | per l'anello di focus. Agisce solo su scrollLeft del contenitore: la pagina non scorre
 | in verticale (al caricamento, con una scheda scelta dal server o via x-model).
 | scrollBy usa coordinate fisiche, quindi funziona anche in RTL.
 */
const revealTab = (tab) => {
    const scroller = tab?.closest('[data-tabs-scroller]');

    if (!scroller || scroller.scrollWidth <= scroller.clientWidth) {
        return;
    }

    const margin = 8;
    const area = scroller.getBoundingClientRect();
    const box = tab.getBoundingClientRect();
    let delta = 0;

    if (box.left < area.left + margin) {
        delta = box.left - area.left - margin;
    } else if (box.right > area.right - margin) {
        delta = box.right - area.right + margin;
    }

    if (delta !== 0) {
        scroller.scrollBy({ left: delta, behavior: 'instant' });
    }
};

document.addEventListener('alpine:init', () => {
    /*
     | Tabs (APG, attivazione automatica): `selected` è il nome della scheda attiva ed è
     | x-modelable, quindi si lega a wire:model / x-model del componente. La scheda attiva
     | resta in vista nella lista scorrevole (revealTab): all'avvio e a ogni cambio, da
     | tastiera, da clic o dal modello.
     */
    window.Alpine.data('uiTabs', (initial = null) => ({
        selected: initial,

        init() {
            const names = this.tabs().map((tab) => tab.dataset.tab);

            if (!names.includes(this.selected)) {
                this.selected = names[0] ?? null;
            }

            this.$watch('selected', () => this.$nextTick(() => this.reveal()));
            this.$nextTick(() => this.reveal());
        },

        reveal() {
            revealTab(this.tabs().find((tab) => tab.dataset.tab === this.selected));
        },

        tabs() {
            return owns(this.$root, '[role="tab"]', '[data-ui-tabs]');
        },

        select(name) {
            this.selected = name;
        },

        isSelected(name) {
            return this.selected === name;
        },

        onKeydown(event) {
            const tabs = this.tabs();
            const index = tabs.indexOf(event.target);

            if (index === -1) {
                return;
            }

            const target = targetIndex(event.key, index, tabs.length, 'ArrowLeft', 'ArrowRight');

            if (target === null) {
                return;
            }

            event.preventDefault();
            tabs[target].focus({ preventScroll: true });
            revealTab(tabs[target]);
            this.select(tabs[target].dataset.tab);
        },
    }));

    /*
     | Accordion (APG): `expanded` è l'elenco dei nomi aperti. Nella modalità singola ne
     | resta al massimo uno. Frecce su/giù e Home/End spostano il focus tra i bottoni.
     */
    window.Alpine.data('uiAccordion', (multiple = false) => ({
        multiple,
        expanded: [],

        init() {
            const open = this.triggers()
                .filter((trigger) => trigger.getAttribute('aria-expanded') === 'true')
                .map((trigger) => trigger.dataset.accordionTrigger);

            this.expanded = this.multiple ? open : open.slice(0, 1);
        },

        triggers() {
            return owns(this.$root, '[data-accordion-trigger]', '[data-ui-accordion]');
        },

        isOpen(name) {
            return this.expanded.includes(name);
        },

        toggle(name) {
            if (this.isOpen(name)) {
                this.expanded = this.expanded.filter((open) => open !== name);
            } else {
                this.expanded = this.multiple ? [...this.expanded, name] : [name];
            }
        },

        onKeydown(event) {
            const triggers = this.triggers();
            const index = triggers.indexOf(event.target);

            if (index === -1) {
                return;
            }

            const target = targetIndex(event.key, index, triggers.length, 'ArrowUp', 'ArrowDown');

            if (target === null) {
                return;
            }

            event.preventDefault();
            triggers[target].focus();
        },
    }));
});
