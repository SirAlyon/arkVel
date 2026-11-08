/*
 | Gruppo "pickers": combobox e date-picker (x-ui.*).
 |
 | - `uiCombobox`   → <x-ui.combobox> (pattern APG "combobox con listbox popup",
 |                    autocompletamento a lista, aria-activedescendant);
 | - `uiDatePicker` → <x-ui.date-picker> (wrapper di Cally). Cally arriva con import
 |                    dinamico, solo quando un date-picker si inizializza: le pagine
 |                    senza date-picker non lo scaricano.
 |
 | I due popup (lista e calendario) si aprono nel top layer (popover="manual") e li
 | posiziona x-anchor con strategia `fixed`: non vengono tagliati dai contenitori con
 | overflow (dialog, drawer). Il valore è sempre `value`, legato con x-modelable.
 */
import '../../css/ui/pickers.css';

/** Testo confrontabile: senza accenti, minuscolo, spazi ai lati tolti. */
const normalize = (text) =>
    String(text ?? '')
        .normalize('NFD')
        .replace(/\p{Diacritic}/gu, '')
        .toLocaleLowerCase()
        .trim();

const showPopover = (element) => {
    if (typeof element.showPopover === 'function' && element.isConnected && !element.matches(':popover-open')) {
        element.showPopover();
    }
};

const hidePopover = (element) => {
    if (typeof element.hidePopover === 'function' && element.matches(':popover-open')) {
        element.hidePopover();
    }
};

/** Ritardo dell'annuncio dei risultati: si parla solo quando si smette di scrivere. */
const ANNOUNCE_DELAY = 500;
/** Ritardo della ricerca lato server ($wire.set). */
const SEARCH_DELAY = 300;

/** Cally, caricato una volta sola e solo quando serve. */
let cally = null;
const loadCally = () => {
    cally ??= import('cally').then(() => customElements.whenDefined('calendar-date'));

    return cally;
};

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    /*
     | Combobox (https://www.w3.org/WAI/ARIA/apg/patterns/combobox/examples/combobox-autocomplete-list/).
     | Il focus resta sempre nell'input; l'opzione attiva è indicata con
     | aria-activedescendant. `filtering` vale true solo mentre si scrive: riaprendo la
     | lista dopo una scelta si vedono tutte le opzioni.
     */
    Alpine.data('uiCombobox', ({ value = '', label = '', search = null, messages = {} } = {}) => ({
        value: value === null ? '' : String(value),
        label,
        query: label,
        open: false,
        filtering: false,
        activeId: null,
        visibleIds: [],
        announcement: '',
        announceTimer: null,
        searchTimer: null,
        observer: null,

        init() {
            this.refresh(false);

            // Valore cambiato da fuori (wire:model, x-model): il testo segue la scelta.
            this.$watch('value', (current) => {
                const option = this.options().find((item) => item.dataset.value === String(current ?? ''));
                this.label = option?.dataset.label ?? (current === '' || current === null ? '' : this.label);

                if (!this.open) {
                    this.query = this.label;
                }
            });

            // Ricerca lato server: Livewire sostituisce le opzioni, la lista si ricalcola.
            if (search !== null) {
                this.observer = new MutationObserver(() => this.refresh(this.open));
                this.observer.observe(this.$refs.listbox, { childList: true });
            }
        },

        destroy() {
            clearTimeout(this.announceTimer);
            clearTimeout(this.searchTimer);
            this.observer?.disconnect();
        },

        options() {
            return [...this.$refs.listbox.querySelectorAll('[role="option"]')];
        },

        visibleOptions() {
            return this.options().filter((option) => this.visibleIds.includes(option.id));
        },

        isVisible(option) {
            return this.visibleIds.includes(option.id);
        },

        isSelected(option) {
            return option.dataset.value === String(this.value ?? '');
        },

        /** Ricalcola le opzioni visibili (filtro lato client) e, se serve, annuncia il conteggio. */
        refresh(announce = true) {
            const query = normalize(this.query);
            const clientFilter = search === null && this.filtering && query !== '';

            this.visibleIds = this.options()
                .filter((option) => !clientFilter || normalize(option.dataset.search).includes(query))
                .map((option) => option.id);

            if (this.activeId !== null && !this.visibleIds.includes(this.activeId)) {
                this.activeId = null;
            }

            if (announce) {
                this.announce();
            }
        },

        announce() {
            clearTimeout(this.announceTimer);
            this.announceTimer = setTimeout(() => {
                const count = this.visibleIds.length;
                this.announcement = count === 0 ? messages.none : count === 1 ? messages.one : messages.many.replace(':count', String(count));
            }, ANNOUNCE_DELAY);
        },

        show() {
            if (this.open || this.$refs.input.disabled) {
                return;
            }

            this.open = true;
            this.refresh();
            this.$nextTick(() => {
                this.$refs.popup.style.width = `${this.$refs.box.offsetWidth}px`;
                showPopover(this.$refs.popup);
            });
        },

        /** Chiude la lista; con `commit` conferma il testo (scelta esatta o ritorno alla scelta). */
        close({ commit = true } = {}) {
            if (!this.open) {
                if (commit) {
                    this.commit();
                }

                return;
            }

            hidePopover(this.$refs.popup);
            this.open = false;
            this.activeId = null;
            clearTimeout(this.announceTimer);
            this.announcement = '';

            if (commit) {
                this.commit();
            }
        },

        toggle() {
            if (this.open) {
                this.close();
            } else {
                this.show();
            }

            this.$refs.input.focus();
        },

        commit() {
            if (normalize(this.query) === '') {
                this.select(null);

                return;
            }

            const exact = this.options().find((option) => normalize(option.dataset.label) === normalize(this.query));

            if (exact) {
                this.select(exact);
            } else {
                this.query = this.label;
                this.filtering = false;
            }
        },

        select(option) {
            this.value = option ? option.dataset.value : '';
            this.label = option ? option.dataset.label : '';
            this.query = this.label;
            this.filtering = false;
        },

        /** Clic su un'opzione: sceglie, chiude e lascia il focus nell'input. */
        choose(option) {
            this.select(option);
            this.close({ commit: false });
            this.$refs.input.focus();
        },

        typed() {
            this.filtering = true;
            this.activeId = null;

            if (this.open) {
                this.refresh();
            } else {
                this.show();
            }

            if (search !== null && this.$wire) {
                clearTimeout(this.searchTimer);
                this.searchTimer = setTimeout(() => this.$wire.set(search, this.query), SEARCH_DELAY);
            }
        },

        activate(option) {
            this.activeId = option?.id ?? null;
            this.$nextTick(() => option?.scrollIntoView({ block: 'nearest' }));
        },

        /** ↓ / ↑: aprono la lista (Alt+↓ senza muoversi) e spostano l'opzione attiva, in modo circolare. */
        move(direction, event) {
            const wasOpen = this.open;
            this.show();

            if (event.altKey) {
                return;
            }

            this.$nextTick(() => {
                const options = this.visibleOptions();

                if (options.length === 0) {
                    return;
                }

                const current = options.findIndex((option) => option.id === this.activeId);

                if (current === -1) {
                    const selected = options.find((option) => this.isSelected(option));
                    this.activate(selected ?? (direction === 'next' ? options[0] : options[options.length - 1]));

                    return;
                }

                if (!wasOpen) {
                    return;
                }

                const next = direction === 'next' ? (current + 1) % options.length : (current - 1 + options.length) % options.length;
                this.activate(options[next]);
            });
        },

        /** Home / End: prima / ultima opzione quando una è attiva; altrimenti muovono il cursore nel testo. */
        edge(which, event) {
            if (!this.open || this.activeId === null) {
                return;
            }

            const options = this.visibleOptions();
            event.preventDefault();
            this.activate(which === 'first' ? options[0] : options[options.length - 1]);
        },

        enter(event) {
            if (!this.open) {
                return;
            }

            event.preventDefault();
            const active = this.activeId !== null ? document.getElementById(this.activeId) : null;

            if (active) {
                this.choose(active);
            } else {
                this.close();
            }
        },

        /** Esc: chiude la lista lasciando il testo; a lista chiusa svuota il campo. */
        escape(event) {
            if (this.open) {
                event.preventDefault();
                event.stopPropagation();
                this.close({ commit: false });
            } else if (this.query !== '' || this.value !== '') {
                event.preventDefault();
                event.stopPropagation();
                this.clear();
            }
        },

        clear() {
            this.select(null);
            this.refresh(false);

            if (search !== null && this.$wire) {
                clearTimeout(this.searchTimer);
                this.$wire.set(search, '');
            }

            this.$refs.input.focus();
        },

        focusLeft(event) {
            if (!this.$root.contains(event.relatedTarget)) {
                this.close();
            }
        },
    }));

    /*
     | Date picker. `value` è la data ISO (Y-m-d, '' se vuota). Il calendario è un
     | <calendar-date> di Cally (x-ref="calendar"); `isDateDisallowed` si imposta solo dopo
     | la definizione del web component, altrimenti la proprietà nasconderebbe il setter.
     */
    Alpine.data('uiDatePicker', ({ value = '', min = '', max = '', locale = 'it', inline = false, disallowed = {}, messages = {} } = {}) => {
        const formatter = new Intl.DateTimeFormat(locale, { dateStyle: 'long' });
        const monthFormatter = new Intl.DateTimeFormat(locale, { month: 'long', year: 'numeric', timeZone: 'UTC' });

        /**
         * Giorno su cui stare nel calendario per una data: la data stessa (se è una data ISO
         * Y-m-d), altrimenti oggi, dentro min / max (le date ISO si confrontano come stringhe).
         */
        const focusFor = (date) => {
            const now = new Date();
            const today = [now.getFullYear(), String(now.getMonth() + 1).padStart(2, '0'), String(now.getDate()).padStart(2, '0')].join('-');
            let focus = /^\d{4}-\d{2}-\d{2}$/.test(String(date ?? '')) ? date : today;
            focus = min && focus < min ? min : focus;
            focus = max && focus > max ? max : focus;

            return focus;
        };

        /** Titolo "mese anno" del mese che contiene un giorno ISO. */
        const headingFor = (iso) => monthFormatter.format(new Date(`${iso}T00:00:00Z`));

        return {
            value: value ?? '',
            open: false,
            announcement: '',
            heading: headingFor(focusFor(value)),

            init() {
                loadCally().then(() => this.configure());
                // Valore cambiato da fuori (x-model, wire:model, clear()): Cally non emette
                // focusday, quindi pagina del calendario e titolo seguono la stessa fonte qui.
                this.$watch('value', (date) => this.follow(date));
            },

            /** Porta calendario e titolo sul mese di una data (oggi se vuota), dentro min / max. */
            follow(date) {
                const focus = focusFor(date);
                this.heading = headingFor(focus);
                // focusedDate si imposta solo dopo la definizione del web component (come isDateDisallowed).
                loadCally().then(() => {
                    this.$refs.calendar.focusedDate = focus;
                });
            },

            configure() {
                const dates = disallowed.dates ?? [];
                const weekdays = disallowed.weekdays ?? [];

                if (dates.length > 0 || weekdays.length > 0) {
                    // Cally passa una Date in UTC (mezzanotte del giorno).
                    this.$refs.calendar.isDateDisallowed = (date) => weekdays.includes(date.getUTCDay()) || dates.includes(date.toISOString().slice(0, 10));
                }
            },

            format(iso) {
                const [year, month, day] = String(iso ?? '').split('-').map(Number);

                return year && month && day ? formatter.format(new Date(year, month - 1, day)) : '';
            },

            get display() {
                return this.format(this.value);
            },

            get status() {
                return this.value ? messages.selected.replace(':date', this.display) : messages.none;
            },

            toggle() {
                if (this.open) {
                    this.close();
                } else {
                    this.show();
                }
            },

            show() {
                if (inline || this.open || this.$refs.trigger.disabled) {
                    return;
                }

                this.open = true;
                this.$nextTick(() => {
                    showPopover(this.$refs.panel);
                    // Focus sul giorno scelto (o su oggi): ci pensa calendar-date.focus().
                    loadCally().then(() => requestAnimationFrame(() => this.open && this.$refs.calendar.focus()));
                });
            },

            close(returnFocus = true) {
                if (!this.open) {
                    return;
                }

                hidePopover(this.$refs.panel);
                this.open = false;

                if (returnFocus) {
                    this.$refs.trigger.focus();
                }
            },

            /** Evento `focusday` di Cally (frecce, PagSu / PagGiù, mese precedente / successivo): aggiorna il titolo. */
            focused(event) {
                if (event.detail instanceof Date) {
                    this.heading = monthFormatter.format(event.detail);
                }
            },

            /** Evento `change` di Cally: nuova data scelta (clic, Invio o Spazio su un giorno). */
            select(event) {
                this.value = event.target.value;

                if (!inline) {
                    this.announcement = this.status;
                    this.close();
                }
            },

            clear() {
                this.value = '';
                this.announcement = messages.none;
                this.$refs.input.focus();
            },

            /** Esc chiude solo se il calendario è aperto: altrimenti lascia l'evento al dialog che lo contiene. */
            escape(event) {
                if (this.open) {
                    event.preventDefault();
                    event.stopPropagation();
                    this.close();
                }
            },

            /** Focus fuori dal componente: chiude; se il focus va "nel vuoto" torna al bottone. */
            focusLeft(event) {
                if (this.open && !this.$root.contains(event.relatedTarget)) {
                    this.close(event.relatedTarget === null);
                }
            },

            clickedOutside() {
                if (this.open) {
                    this.close(document.activeElement === document.body || this.$root.contains(document.activeElement));
                }
            },
        };
    });
});
