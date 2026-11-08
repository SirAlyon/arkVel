/*
 | Gruppo "overlays": dialog, drawer, dropdown, tooltip, toast (x-ui.*).
 |
 | - `uiDialog`   → <x-ui.dialog> e <x-ui.drawer> (stesso meccanismo: <dialog> nativo con
 |                  showModal(), che dà focus trap, inert e Esc);
 | - `uiDropdown` → <x-ui.dropdown> (pattern APG "menu button");
 | - `uiTooltip`  → <x-ui.tooltip> (WCAG 1.4.13: hover E focus, Esc, resta se ci passi sopra);
 | - store `toasts` + `uiToaster` → <x-ui.toaster> / <x-ui.toast>. API:
 |     window.dispatchEvent(new CustomEvent('toast', { detail: { type, title, description } }))
 |     $dispatch('toast', { type: 'success', title: '…' })            (Alpine)
 |     $this->dispatch('toast', type: 'success', title: '…')           (Livewire)
 | - `uiToasterOutlet` → <x-ui.toaster.outlet>, dentro ogni dialog e drawer: i toast
 |   lanciati con un modale aperto si vedono, si annunciano e si chiudono da lì (vedi sotto);
 | - `uiToast` → <x-ui.toast> statico (chiusura con focus spostato, WCAG 2.4.3).
 |
 | Menu e tooltip si aprono nel top layer (popover="manual"), così non vengono tagliati
 | dai contenitori con overflow (dialog, drawer) e stanno sopra ai dialog aperti; la
 | posizione la calcola x-anchor con strategia `fixed`.
 */
import '../../css/ui/overlays.css';

/** Durata di un token di motion (--duration-*) in ms: ~0 con prefers-reduced-motion. */
const duration = (token) => {
    const value = getComputedStyle(document.documentElement).getPropertyValue(`--duration-${token}`).trim();
    const amount = Number.parseFloat(value);

    if (Number.isNaN(amount)) {
        return 0;
    }

    return value.endsWith('ms') ? amount : amount * 1000;
};

/** Blocco dello scroll della pagina, condiviso tra dialog e drawer annidati. */
const scrollLock = {
    holders: 0,
    lock() {
        if (this.holders++ === 0) {
            const style = document.documentElement.style;
            style.scrollbarGutter = 'stable';
            style.overflow = 'hidden';
        }
    },
    unlock() {
        if (this.holders > 0 && --this.holders === 0) {
            const style = document.documentElement.style;
            style.overflow = '';
            style.scrollbarGutter = '';
        }
    },
};

/** Il clic è fuori dal riquadro del dialog, cioè sul ::backdrop (o sulla scrollbar no). */
const isOutside = (element, event) => {
    const box = element.getBoundingClientRect();

    return event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom;
};

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

/** Elementi che possono ricevere il focus da tastiera. */
const FOCUSABLE = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    'summary',
    '[tabindex]:not([tabindex="-1"])',
    '[contenteditable="true"]',
].join(', ');

/**
 | Dove mettere il focus quando `element` (che lo contiene) sta per sparire: il primo
 | elemento focalizzabile che lo segue, altrimenti l'ultimo che lo precede. Cerca solo nel
 | modale aperto che lo contiene, se c'è (il resto della pagina è inerte).
 */
const focusAround = (element) => {
    const scope = element.closest('dialog[open]') ?? document;
    const candidates = [...scope.querySelectorAll(FOCUSABLE)].filter(
        (candidate) => !element.contains(candidate) && !candidate.closest('[inert]') && candidate.checkVisibility?.() !== false,
    );
    const following = candidates.find((candidate) => element.compareDocumentPosition(candidate) & Node.DOCUMENT_POSITION_FOLLOWING);

    return following ?? candidates.filter((candidate) => element.compareDocumentPosition(candidate) & Node.DOCUMENT_POSITION_PRECEDING).at(-1) ?? null;
};

/** Sostituisce i segnaposto :nome di un testo tradotto. */
const format = (pattern, values) => pattern.replace(/:([a-z]+)/g, (match, key) => (key in values ? values[key] : match));

const TOAST_TYPES = ['success', 'warning', 'danger', 'info'];

/** Numeratore dei dialog e drawer, per la pila dei modali aperti (store `toasts`). */
let dialogSequence = 0;

/*
 | Parte comune delle due regioni dei toast (<x-ui.toaster> e l'outlet dentro i modali):
 | pausa su hover e focus, chiusura dal bottone con il focus spostato sul toast vicino o
 | riportato dov'era prima (WCAG 2.4.3). La lista ha x-ref="toastList"; `speaks` dice se
 | la regione è quella attiva (annunci e lista visibile). Alpine (da Livewire) è
 | window.Alpine: esiste quando le regioni si inizializzano.
 */
const toastRegion = () => ({
    focusBefore: null,

    entered(event) {
        if (!this.$refs.toastList.contains(event.relatedTarget) && event.relatedTarget instanceof HTMLElement) {
            this.focusBefore = event.relatedTarget;
        }
        window.Alpine.store('toasts').pause();
    },

    left(event) {
        if (!this.$refs.toastList.contains(event.relatedTarget)) {
            window.Alpine.store('toasts').resume();
        }
    },

    pointerLeft() {
        if (!this.$refs.toastList.contains(document.activeElement)) {
            window.Alpine.store('toasts').resume();
        }
    },

    /** Chiusura dal bottone: il focus va al toast vicino o torna dove era prima. */
    closeToast(id, event) {
        const current = event.target.closest('[data-toast]');
        const others = [...this.$refs.toastList.querySelectorAll('[data-toast]:not([data-leaving]) [data-toast-close]')].filter((button) => !current?.contains(button));

        window.Alpine.store('toasts').dismiss(id);

        const before = this.focusBefore?.isConnected ? this.focusBefore : null;
        const next = others[0] ?? before ?? (current ? focusAround(current) : null);

        if (next) {
            next.focus();
        } else {
            window.Alpine.store('toasts').resume();
        }
    },
});

/** Timer di chiusura dei toast, fuori dallo stato reattivo: id → { handle, remaining, startedAt }. */
const toastTimers = new Map();

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    /*
     | Dialog e drawer. `isOpen` è il modello (x-modelable): x-model / wire:model lo legano
     | a una variabile Alpine o a una proprietà Livewire booleana. Il nome è volutamente
     | poco comune: x-model si valuta nello scope del componente, e una variabile esterna
     | con lo stesso nome verrebbe nascosta.
     |
     | - ogni modale aperto entra nella pila `layers` dello store `toasts`: l'outlet dei
     |   toast del modale in cima (`onTop`) mostra e annuncia i toast al posto del toaster
     |   della pagina, che showModal() ha reso inerte;
     | - intestazione e footer fissi (figli con data-sticky="top|bottom") riservano il loro
     |   spazio con scroll-padding sul <dialog> che scorre, misurato con ResizeObserver:
     |   l'elemento con il focus non finisce sotto di loro (WCAG 2.4.11, tecnica C43).
     */
    Alpine.data('uiDialog', ({ name = null, dismissible = true, open = false } = {}) => ({
        isOpen: open,
        locked: false,
        layer: 0,
        returnFocus: null,
        pressedOutside: false,
        stickyObserver: null,

        init() {
            this.layer = ++dialogSequence;
            this.$watch('isOpen', (value) => (value ? this.showModal() : this.closeModal()));
            this.observeSticky();

            if (this.isOpen) {
                this.$nextTick(() => this.showModal());
            }
        },

        destroy() {
            this.stickyObserver?.disconnect();
            this.release();
        },

        /** Questo è il modale aperto più in alto: i toast passano dal suo outlet. */
        get onTop() {
            return this.isOpen && Alpine.store('toasts').layers.at(-1) === this.layer;
        },

        show() {
            this.isOpen = true;
        },

        close() {
            this.isOpen = false;
        },

        /** Evento con nome: $dispatch('open-dialog', 'nome') oppure { name: 'nome' } (Livewire). */
        targets(event) {
            const detail = event.detail;

            return name !== null && (detail === name || detail?.name === name);
        },

        showModal() {
            const dialog = this.$refs.dialog;

            if (dialog.open) {
                return;
            }

            this.returnFocus = document.activeElement;
            dialog.showModal();
            Alpine.store('toasts').cover(this.layer);

            if (!this.locked) {
                scrollLock.lock();
                this.locked = true;
            }

            // Focus iniziale: l'elemento con autofocus (es. "Annulla" nei dialog di conferma),
            // altrimenti il bottone di chiusura.
            (dialog.querySelector('[autofocus]') ?? dialog.querySelector('[data-dialog-close]') ?? dialog).focus();
        },

        closeModal() {
            if (this.$refs.dialog.open) {
                this.$refs.dialog.close();
            }
        },

        /** Evento nativo `close` (Esc, close(), form method="dialog"). */
        closed() {
            this.isOpen = false;
            this.release();

            const target = this.returnFocus;
            this.returnFocus = null;

            if (target instanceof HTMLElement && target.isConnected) {
                target.focus();
            }
        },

        /** Libera il blocco dello scroll e il posto nella pila dei modali. */
        release() {
            Alpine.store('toasts').uncover(this.layer);

            if (this.locked) {
                scrollLock.unlock();
                this.locked = false;
            }
        },

        observeSticky() {
            const dialog = this.$refs.dialog;
            const sticky = [...dialog.querySelectorAll(':scope > * > [data-sticky]')];

            if (sticky.length === 0) {
                return;
            }

            const extent = (edge) => sticky.filter((element) => element.dataset.sticky === edge).reduce((total, element) => total + element.getBoundingClientRect().height, 0);
            const padding = (height) => (height > 0 ? `${Math.ceil(height) + 8}px` : '');

            this.stickyObserver = new ResizeObserver(() => {
                dialog.style.scrollPaddingTop = padding(extent('top'));
                dialog.style.scrollPaddingBottom = padding(extent('bottom'));
            });
            sticky.forEach((element) => this.stickyObserver.observe(element));
        },

        pointerDown(event) {
            this.pressedOutside = isOutside(this.$refs.dialog, event);
        },

        /** Clic sul backdrop: chiude, salvo `dismissible: false` (dialog di conferma). */
        clicked(event) {
            const outside = this.pressedOutside && event.target === this.$refs.dialog && isOutside(this.$refs.dialog, event);
            this.pressedOutside = false;

            if (dismissible && outside) {
                this.close();
            }
        },
    }));

    /*
     | Menu button (https://www.w3.org/WAI/ARIA/apg/patterns/menu-button/): frecce, Home/End,
     | lettera iniziale, Esc (chiude e torna al trigger), Tab (chiude e prosegue).
     */
    Alpine.data('uiDropdown', () => ({
        open: false,

        init() {
            this.$watch('open', (value) => {
                if (!value) {
                    hidePopover(this.$refs.menu);
                }
            });
        },

        items() {
            return [...this.$refs.menu.querySelectorAll('[role="menuitem"]')];
        },

        toggle() {
            if (this.open) {
                this.close();
            } else {
                this.show('first');
            }
        },

        show(focus = 'first') {
            this.open = true;
            this.$nextTick(() => {
                showPopover(this.$refs.menu);
                this.focusItem(focus);
            });
        },

        close(returnFocus = true) {
            if (!this.open) {
                return;
            }

            hidePopover(this.$refs.menu);
            this.open = false;

            if (returnFocus) {
                this.$refs.trigger.focus();
            }
        },

        focusItem(which) {
            const items = this.items();

            if (items.length === 0) {
                this.$refs.menu.focus();

                return;
            }

            const current = items.indexOf(document.activeElement);
            const index = {
                first: 0,
                last: items.length - 1,
                next: (current + 1) % items.length,
                previous: (current - 1 + items.length) % items.length,
            }[which];

            items[index].focus();
        },

        /** Esc chiude solo se il menu è aperto: altrimenti lascia l'evento al dialog che lo contiene. */
        escape(event) {
            if (this.open) {
                event.preventDefault();
                event.stopPropagation();
                this.close();
            }
        },

        typeahead(event) {
            if (event.key.length !== 1 || event.key === ' ' || event.ctrlKey || event.metaKey || event.altKey) {
                return;
            }

            const items = this.items();
            const start = items.indexOf(document.activeElement) + 1;
            const key = event.key.toLocaleLowerCase();
            const ordered = [...items.slice(start), ...items.slice(0, start)];
            const match = ordered.find((item) => item.textContent.trim().toLocaleLowerCase().startsWith(key));

            match?.focus();
        },

        /** Voci disattivate (aria-disabled): restano focalizzabili ma non si attivano. */
        blockDisabled(event) {
            const item = event.target.closest('[role="menuitem"]');

            if (item?.getAttribute('aria-disabled') === 'true') {
                event.preventDefault();
                event.stopImmediatePropagation();
            }
        },

        activated(event) {
            if (event.target.closest('[role="menuitem"]')) {
                this.close();
            }
        },

        /** Spazio su una voce link: i link non si attivano da soli con lo spazio. */
        space(event) {
            const item = event.target.closest('a[role="menuitem"]');

            if (item) {
                event.preventDefault();
                item.click();
            }
        },

        focusLeft(event) {
            if (this.open && !this.$root.contains(event.relatedTarget)) {
                this.close(false);
            }
        },
    }));

    /*
     | Tooltip: compare dopo un breve ritardo al passaggio del mouse, subito al focus;
     | resta visibile se il puntatore passa sopra il tooltip; Esc lo nasconde senza
     | spostare il focus. Il trigger riceve aria-describedby.
     */
    Alpine.data('uiTooltip', ({ delay = 150, grace = 100 } = {}) => ({
        hovered: false,
        focused: false,
        dismissed: false,
        trigger: null,
        timer: null,

        get visible() {
            return (this.hovered || this.focused) && !this.dismissed;
        },

        init() {
            this.trigger = this.$root.querySelector('a[href], button, input, select, textarea, [tabindex]:not([tabindex="-1"])') ?? this.$root.firstElementChild;
            // L'id del tooltip lo assegna x-bind sul figlio, dopo init(): si collega al tick dopo.
            this.$nextTick(() => {
                const ids = (this.trigger?.getAttribute('aria-describedby') ?? '').split(' ').filter(Boolean);
                this.trigger?.setAttribute('aria-describedby', [...new Set([...ids, this.$refs.tooltip.id])].join(' '));
            });

            this.$watch('visible', (value) => {
                if (value) {
                    this.$nextTick(() => showPopover(this.$refs.tooltip));
                } else {
                    hidePopover(this.$refs.tooltip);
                }
            });
        },

        pointer(inside) {
            clearTimeout(this.timer);
            this.timer = setTimeout(() => {
                if (inside && !this.hovered) {
                    this.dismissed = false;
                }
                this.hovered = inside;
            }, inside ? delay : grace);
        },

        focus(inside) {
            if (inside) {
                this.dismissed = false;
            }
            this.focused = inside;
        },

        dismiss(event = null) {
            if (!this.visible) {
                return;
            }

            // Dentro un dialog, il primo Esc chiude solo il tooltip.
            event?.preventDefault();
            event?.stopPropagation();
            this.dismissed = true;
        },
    }));

    /*
     | Notifiche toast. Durata predefinita per tipo (ms, 0 = resta finché non la chiudi):
     | gli errori non si chiudono da soli. La pausa (hover o focus nella regione) ferma
     | tutti i timer.
     |
     | `layers` è la pila dei dialog e drawer aperti (id di uiDialog): quando non è vuota,
     | annunci e lista passano all'outlet del modale in cima. A ogni cambio della pila le
     | regioni live si svuotano, così un modale che si apre non rilegge un annuncio vecchio.
     | `messages` (dal toaster): nomi tradotti dei tipi e schemi dell'annuncio con i
     | segnaposto :type, :title, :description.
     */
    Alpine.store('toasts', {
        items: [],
        polite: '',
        assertive: '',
        layers: [],
        messages: { types: {}, announcement: '', announcementShort: '' },
        limit: 5,
        durations: { success: 5000, info: 6000, warning: 8000, danger: 0 },
        paused: false,
        sequence: 0,

        /** Accetta { type, title, description, duration } (anche come [ {...} ] da Livewire). */
        add(input) {
            const detail = Array.isArray(input) ? input[0] : input;

            if (!detail || typeof detail.title !== 'string' || detail.title.trim() === '') {
                return null;
            }

            const type = TOAST_TYPES.includes(detail.type) ? detail.type : 'info';
            const toast = {
                id: ++this.sequence,
                type,
                title: detail.title,
                description: typeof detail.description === 'string' ? detail.description : '',
                duration: Number.isFinite(detail.duration) ? detail.duration : this.durations[type],
                leaving: false,
            };

            this.items.push(toast);

            const active = this.items.filter((item) => !item.leaving);
            active.slice(0, Math.max(0, active.length - this.limit)).forEach((item) => this.dismiss(item.id));

            this.announce(toast);

            if (!this.paused) {
                this.start(toast.id);
            }

            return toast.id;
        },

        /** Testo per le regioni live: role="status" (polite) o role="alert" per gli errori. */
        announce(toast) {
            const region = toast.type === 'danger' ? 'assertive' : 'polite';
            const values = { type: this.messages.types[toast.type] ?? '', title: toast.title, description: toast.description };
            const pattern = toast.description ? this.messages.announcement : this.messages.announcementShort;
            const text = pattern ? format(pattern, values) : [values.type, values.title, values.description].filter(Boolean).join(' ');

            this[region] = '';
            setTimeout(() => {
                this[region] = text;
            }, 100);
        },

        find(id) {
            return this.items.find((item) => item.id === id);
        },

        start(id) {
            const toast = this.find(id);

            if (!toast || toast.leaving || !toast.duration) {
                return;
            }

            const timer = toastTimers.get(id) ?? { remaining: toast.duration };
            clearTimeout(timer.handle);
            timer.startedAt = Date.now();
            timer.handle = setTimeout(() => this.dismiss(id), timer.remaining);
            toastTimers.set(id, timer);
        },

        pause() {
            this.paused = true;
            toastTimers.forEach((timer) => {
                if (timer.handle) {
                    clearTimeout(timer.handle);
                    timer.handle = null;
                    timer.remaining = Math.max(1000, timer.remaining - (Date.now() - timer.startedAt));
                }
            });
        },

        resume() {
            this.paused = false;
            this.items.forEach((toast) => this.start(toast.id));
        },

        dismiss(id) {
            const toast = this.find(id);

            if (!toast || toast.leaving) {
                return;
            }

            clearTimeout(toastTimers.get(id)?.handle);
            toastTimers.delete(id);
            toast.leaving = true;

            setTimeout(() => {
                this.items = this.items.filter((item) => item.id !== id);
            }, duration('base'));
        },

        clear() {
            this.items.forEach((toast) => this.dismiss(toast.id));
        },

        /** Un dialog o drawer si apre (in cima alla pila) o si chiude. */
        cover(layer) {
            this.layers = [...this.layers.filter((item) => item !== layer), layer];
            this.silence();
        },

        uncover(layer) {
            if (this.layers.includes(layer)) {
                this.layers = this.layers.filter((item) => item !== layer);
                this.silence();
            }
        },

        silence() {
            this.polite = '';
            this.assertive = '';
        },
    });

    window.addEventListener('toast', (event) => Alpine.store('toasts').add(event.detail));

    /*
     | Regione dei toast della pagina (<x-ui.toaster>): pausa su hover/focus, ritorno del
     | focus quando si chiude il toast che lo conteneva (toastRegion), scroll-padding in fondo
     | alla pagina perché i toast non coprano l'elemento con il focus (WCAG 2.4.11, tecnica
     | C43). Con un modale aperto tace e nasconde la lista: è inerte, parla l'outlet.
     */
    Alpine.data('uiToaster', ({ messages = {}, flash = null } = {}) => ({
        ...toastRegion(),
        observer: null,

        init() {
            const store = Alpine.store('toasts');
            store.messages = { ...store.messages, ...messages };

            this.observer = new ResizeObserver(() => this.reserveSpace());
            this.observer.observe(this.$refs.toastList);

            if (flash) {
                this.$nextTick(() => store.add(flash));
            }
        },

        destroy() {
            this.observer?.disconnect();
            document.documentElement.style.scrollPaddingBottom = '';
        },

        get speaks() {
            return Alpine.store('toasts').layers.length === 0;
        },

        reserveSpace() {
            const height = this.$refs.toastList.getBoundingClientRect().height;
            document.documentElement.style.scrollPaddingBottom = height > 0 ? `${Math.ceil(height) + 32}px` : '';
        },
    }));

    /*
     | Outlet dei toast dentro un dialog o drawer (<x-ui.toaster.outlet>, incluso da
     | x-ui.dialog.shell). showModal() rende inerte tutto ciò che sta fuori dal modale,
     | anche un popover mostrato sopra di lui nel top layer (verificato in Chrome: non
     | riceve focus né clic). L'outlet invece è dentro il <dialog>: quando il suo modale è
     | in cima alla pila (`onTop`, dallo scope di uiDialog) le sue regioni live annunciano e
     | la lista si apre come popover nel top layer, sopra il dialog e il suo ::backdrop,
     | raggiungibile con Tab dopo il contenuto del dialog.
     */
    Alpine.data('uiToasterOutlet', () => ({
        ...toastRegion(),

        init() {
            this.$watch('speaks', (value) => (value ? showPopover(this.$refs.toastList) : hidePopover(this.$refs.toastList)));
        },

        get speaks() {
            return this.onTop;
        },
    }));

    /*
     | Toast statico (<x-ui.toast title="…">): chiudendolo, se il focus era dentro, passa
     | all'elemento indicato da `focusTarget` (selettore CSS; un contenitore non focalizzabile
     | riceve tabindex="-1") o al primo focalizzabile che segue il toast (WCAG 2.4.3).
     */
    Alpine.data('uiToast', ({ focusTarget = null } = {}) => ({
        shown: true,

        dismiss() {
            if (this.$root.contains(document.activeElement)) {
                const target = (focusTarget ? document.querySelector(focusTarget) : null) ?? focusAround(this.$root);

                if (target instanceof HTMLElement) {
                    if (!target.matches(FOCUSABLE) && !target.hasAttribute('tabindex')) {
                        target.setAttribute('tabindex', '-1');
                    }
                    target.focus();
                }
            }

            this.shown = false;
        },
    }));
});
