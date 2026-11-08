/*
 | Shell del sito pubblico (layouts/app, components/site/*).
 |
 | - `siteHeader` → <x-site.header>: `scrolled` diventa vero quando la sentinella in cima al
 |   <body> esce dalla finestra (IntersectionObserver, niente listener di scroll). L'header
 |   lo espone come data-scrolled: bordo `line` e, in modalità trasparente, fondo canvas;
 | - navigazione wire:navigate:
 |   - prima dello scambio di pagina chiude i dialog aperti (menu mobile): Livewire salva
 |     l'HTML della pagina per il tasto Indietro, e un <dialog open> salvato tornerebbe
 |     aperto ma non modale;
 |   - dopo lo scambio annuncia il titolo della nuova pagina nella regione live persistente
 |     del layout ([data-route-announcer]), come farebbe il caricamento di una pagina vera.
 |     Il focus non va spostato: Livewire sostituisce il <body>, quindi il primo Tab parte
 |     dall'inizio del documento e raggiunge lo skip link (tests/Browser/ShellTest.php).
 |
 | Questo modulo è caricato una volta sola (ui.js, nel <head>): i listener su document
 | restano registrati tra una navigazione e l'altra e non vanno ripetuti.
 */
document.addEventListener('alpine:init', () => {
    window.Alpine.data('siteHeader', () => ({
        scrolled: false,
        observer: null,

        init() {
            const sentinel = document.querySelector('[data-site-header-sentinel]');

            if (!sentinel || typeof IntersectionObserver !== 'function') {
                this.scrolled = window.scrollY > 0;

                return;
            }

            this.observer = new IntersectionObserver(([entry]) => {
                this.scrolled = !entry.isIntersecting;
            });
            this.observer.observe(sentinel);
        },

        destroy() {
            this.observer?.disconnect();
        },
    }));
});

document.addEventListener('livewire:navigating', () => {
    document.querySelectorAll('dialog[open]').forEach((dialog) => dialog.close());
});

let firstLoad = true;

document.addEventListener('livewire:navigated', () => {
    // livewire:navigated scatta anche al primo caricamento: lì il titolo lo legge il browser.
    if (firstLoad) {
        firstLoad = false;

        return;
    }

    const announcer = document.querySelector('[data-route-announcer]');

    if (!announcer) {
        return;
    }

    const message = (announcer.dataset.template ?? ':title').replace(':title', document.title);

    // Svuota e riscrive al frame dopo: la stessa pagina due volte va annunciata di nuovo.
    announcer.textContent = '';
    requestAnimationFrame(() => {
        announcer.textContent = message;
    });
});
