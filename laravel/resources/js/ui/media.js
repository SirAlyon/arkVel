/*
 | x-ui · gruppo "media": dati Alpine della galleria (resources/views/components/ui/gallery.blade.php).
 |
 | Questo modulo è nel bundle di ogni pagina (resources/js/ui.js), quindi resta minimo:
 | Embla Carousel e PhotoSwipe (MIT, §2 del piano UI) arrivano con import dinamico solo
 | quando una galleria si inizializza; il core di PhotoSwipe solo all'apertura della lightbox.
 |
 | Il markup del server funziona già senza JS (lista scorrevole, link alle foto intere):
 | qui si aggiungono carosello, miniature, tastiera e lightbox (pattern APG "Carousel").
 */

let carouselModule;
let lightboxModule;

const loadCarousel = () => (carouselModule ??= import('embla-carousel').then((module) => module.default));

const loadLightbox = () =>
    (lightboxModule ??= Promise.all([
        import('photoswipe/lightbox'),
        import('photoswipe/style.css'),
        import('../../css/ui/media.css'),
    ]).then(([module]) => module.default));

// Letto a ogni uso (non una volta sola): segue la preferenza anche se cambia a pagina aperta.
const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/** Animazioni di apertura/chiusura e zoom della lightbox secondo la preferenza attuale. */
const lightboxMotion = () =>
    reducedMotion()
        ? { showHideAnimationType: 'none', zoomAnimationDuration: 0 }
        : { showHideAnimationType: 'zoom', zoomAnimationDuration: 333 };

/**
 | Porta `item` in vista nella sua striscia scorrevole, agendo solo su scrollLeft (la pagina
 | non scorre in verticale). scrollBy usa coordinate fisiche, quindi vale anche in RTL.
 */
const revealInStrip = (item) => {
    const strip = item?.closest('[data-gallery-thumbs]');

    if (!strip || strip.scrollWidth <= strip.clientWidth) {
        return;
    }

    const margin = 8;
    const area = strip.getBoundingClientRect();
    const box = item.getBoundingClientRect();
    let delta = 0;

    if (box.left < area.left + margin) {
        delta = box.left - area.left - margin;
    } else if (box.right > area.right - margin) {
        delta = box.right - area.right + margin;
    }

    if (delta !== 0) {
        strip.scrollBy({ left: delta, behavior: reducedMotion() ? 'instant' : 'smooth' });
    }
};

document.addEventListener('alpine:init', () => {
    window.Alpine.data('uiGallery', (options = {}) => ({
        total: options.total ?? 0,
        labels: options.labels ?? {},
        current: 0,
        ready: false,
        embla: null,
        lightbox: null,

        get atStart() {
            return this.current <= 0;
        },

        get atEnd() {
            return this.current >= this.total - 1;
        },

        async init() {
            const [Carousel, Lightbox] = await Promise.all([
                this.total > 1 ? loadCarousel() : null,
                loadLightbox(),
            ]);

            // La galleria può essere sparita nel frattempo (wire:navigate, morph di Livewire).
            if (!this.$root.isConnected) {
                return;
            }

            const viewport = this.viewport();

            if (Carousel) {
                // Senza JS la lista scorre in modo nativo: si riparte dalla foto che l'utente
                // stava guardando, poi il viewport passa a overflow-hidden (data-ready).
                const width = viewport.clientWidth || 1;
                const startIndex = Math.min(this.total - 1, Math.round(Math.abs(viewport.scrollLeft) / width));

                this.$root.dataset.ready = '';
                viewport.scrollLeft = 0;

                this.embla = Carousel(viewport, {
                    align: 'start',
                    containScroll: 'trimSnaps',
                    direction: this.rtl() ? 'rtl' : 'ltr',
                    duration: 22,
                    startIndex,
                    // Le slide fuori vista sono inert: il focus non le raggiunge.
                    watchFocus: false,
                });
                this.embla.on('select', () => this.sync());
                // Dopo uno swipe Embla scivola fino alla slide con una durata sua (non
                // `duration`): con reduced-motion si salta subito all'arrivo.
                this.embla.on('pointerUp', (api) => {
                    if (reducedMotion()) {
                        api.scrollTo(api.selectedScrollSnap(), true);
                    }
                });
            }

            this.lightbox = this.createLightbox(Lightbox, viewport);
            this.lightbox.init();

            this.ready = true;
            this.sync();
        },

        destroy() {
            this.embla?.destroy();
            this.lightbox?.destroy();
        },

        createLightbox(Lightbox, viewport) {
            const labels = this.labels;
            let pswp = null;

            const lightbox = new Lightbox({
                gallery: viewport,
                children: 'a[data-gallery-zoom]',
                pswpModule: () => import('photoswipe'),
                loop: false,
                bgOpacity: 0.94,
                ...lightboxMotion(),
                // Prima di ogni apertura: la preferenza può essere cambiata dall'ultima volta
                // (e PhotoSwipe riscrive queste opzioni quando la trova attiva).
                openPromise: async () => Object.assign(lightbox.options, lightboxMotion()),
                // Il focus lo restituiamo noi, alla foto mostrata alla chiusura.
                returnFocus: false,
                closeTitle: labels.close,
                zoomTitle: labels.zoom,
                arrowPrevTitle: labels.previous,
                arrowNextTitle: labels.next,
                errorMsg: labels.error,
                indexIndicatorSep: ' / ',
            });

            lightbox.on('afterInit', () => {
                pswp = lightbox.pswp;
                pswp.element.setAttribute('aria-modal', 'true');
                pswp.element.setAttribute('aria-label', labels.dialog);
                pswp.scrollWrap?.setAttribute('aria-roledescription', labels.roledescription);
                pswp.mainScroll.itemHolders.forEach((holder) => holder.el.setAttribute('aria-roledescription', labels.slide));
            });

            // Il focus entra nel dialog: PhotoSwipe ci prova all'avvio, quando il dialog non è
            // ancora visibile, e da tastiera il focus restava sulla foto sotto la lightbox.
            lightbox.on('openingAnimationEnd', () => {
                if (pswp && !pswp.isDestroying && !pswp.opener?.isClosing && !pswp.element.contains(document.activeElement)) {
                    pswp.element.focus();
                }
            });

            // Alla chiusura il carosello va (senza animazione) sulla foto vista per ultima,
            // così l'animazione di uscita torna verso la slide giusta.
            lightbox.on('close', () => {
                if (pswp) {
                    this.goTo(pswp.currIndex, true);
                }
            });

            // Dopo la rimozione della lightbox (e della sua trappola del focus).
            lightbox.on('destroy', () => {
                pswp = null;
                setTimeout(() => this.zoomLink(this.current)?.focus({ preventScroll: true }));
            });

            return lightbox;
        },

        viewport() {
            return this.$root.querySelector('[data-gallery-viewport]');
        },

        slides() {
            return [...this.$root.querySelectorAll('[data-gallery-slide]')];
        },

        zoomLink(index) {
            return this.slides()[index]?.querySelector('[data-gallery-zoom]') ?? null;
        },

        thumb(index) {
            return this.$root.querySelector(`[data-gallery-thumb="${index}"]`);
        },

        rtl() {
            return getComputedStyle(this.$root).direction === 'rtl';
        },

        counter() {
            return (this.labels.counter ?? '')
                .replace(':position', String(this.current + 1))
                .replace(':total', String(this.total));
        },

        /** Stato derivato dalla slide scelta: slide inert, miniatura in vista. */
        sync() {
            if (this.embla) {
                this.current = this.embla.selectedScrollSnap();
            }

            this.slides().forEach((slide, index) => {
                slide.inert = this.ready && this.total > 1 && index !== this.current;
            });

            revealInStrip(this.thumb(this.current));
        },

        goTo(index, jump = false) {
            const target = Math.max(0, Math.min(this.total - 1, index));

            if (this.embla) {
                this.embla.scrollTo(target, jump || reducedMotion());
            }

            this.current = target;
            this.sync();
        },

        previous() {
            if (!this.atStart) {
                this.goTo(this.current - 1);
            }
        },

        next() {
            if (!this.atEnd) {
                this.goTo(this.current + 1);
            }
        },

        open(index) {
            this.lightbox?.loadAndOpen(index);
        },

        /**
         | Frecce sinistra/destra ovunque nella galleria (foto, bottoni, miniature). Il focus
         | segue la foto o la miniatura nuova, perché quella di prima diventa inert.
         */
        onKeydown(event) {
            if (!this.ready || this.total < 2 || this.lightbox?.pswp || !['ArrowLeft', 'ArrowRight'].includes(event.key)) {
                return;
            }

            if (event.altKey || event.ctrlKey || event.metaKey || event.shiftKey) {
                return;
            }

            const onSlide = event.target.closest('[data-gallery-zoom]') !== null;
            const onThumb = event.target.closest('[data-gallery-thumb]') !== null;
            const forward = (event.key === 'ArrowRight') !== this.rtl();

            event.preventDefault();

            if (forward) {
                this.next();
            } else {
                this.previous();
            }

            if (onSlide) {
                this.zoomLink(this.current)?.focus({ preventScroll: true });
            } else if (onThumb) {
                this.thumb(this.current)?.focus({ preventScroll: true });
            }
        },
    }));
});
