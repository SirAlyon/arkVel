/*
 | x-ui · gruppo "motion" (UI-7): reveal allo scroll, foto card → scheda e cuore dei preferiti.
 |
 | Questo modulo è nel bundle di ogni pagina (resources/js/ui.js), quindi resta minimo: la
 | libreria Motion (MIT, §2 del piano UI, `motion/mini`, animazioni WAAPI) arriva con import
 | dinamico solo quando serve, cioè al primo passaggio del puntatore o del focus su un cuore.
 | Tutto il resto è CSS (resources/css/ui/motion.css), con durate ed easing dai token.
 |
 | Con prefers-reduced-motion niente parte: il reveal non nasconde nulla e il cuore non si
 | anima (le durate CSS sono già ~0 in app.css; le animazioni JS vanno saltate qui, perché
 | Motion non legge la media query da solo). La preferenza è letta a ogni uso, così segue
 | un cambio a pagina aperta.
 |
 | ── x-reveal ──────────────────────────────────────────────────────────────────────────
 | Direttiva Alpine: l'elemento compare con una dissolvenza e un piccolo spostamento verso
 | l'alto (--duration-reveal, --ease-out) quando entra nella finestra. Regole:
 | - il markup del server è sempre visibile: lo stato «nascosto» (data-reveal-state=pending)
 |   lo mette solo questo modulo, quindi senza JS non si nasconde niente;
 | - mai sopra la piega: alla prima notifica dell'IntersectionObserver (a font caricati,
 |   quando le altezze sono quelle finali) un elemento già in vista resta com'è (niente
 |   lampeggio), e così l'immagine LCP non viene mai nascosta. Gli elementi sotto la piega
 |   diventano «pending» prima di essere visti;
 | - niente CLS: si animano solo opacity e transform, che non spostano il layout;
 | - gli elementi rivelati nello stesso momento (una riga di card) entrano in sequenza
 |   (--reveal-order, al massimo 4 passi);
 | - un elemento «pending» che riceve il focus o va in stampa è subito visibile (motion.css);
 | - se Livewire rifà il markup (morph), l'attributo di stato sparisce e l'elemento resta
 |   visibile: l'unico modo di sbagliare è mostrare, mai nascondere.
 | x-reveal funziona anche fuori da un componente Alpine o Livewire (init selector). Si
 | spegne con config('arkvel.reveal') = false: <html data-reveal="off"> (vedi revealOff()),
 | l'interruttore esplicito usato anche dai test browser che non riguardano il motion.
 |
 | ── Foto card → scheda (View Transitions di wire:navigate) ──────────────────────────────
 | Le foto degli animali hanno wire:transition.navigate="animal-photo-<id>" e
 | data-photo-transition: il link che le apre (card, hero della home) o vuoto per il soggetto
 | della pagina (galleria della scheda). Livewire dà il nome a tutte, ma in partenza ne deve
 | restare una sola, quella della destinazione: altrimenti anche le altre card che
 | ricompaiono nella pagina nuova (es. tra gli animali simili) volerebbero attraverso lo
 | schermo. Prima della navigazione (livewire:navigate, con l'URL di destinazione) restano
 | le foto il cui link porta lì o, se nessuna, il soggetto della pagina; alle altre un
 | view-transition-name: none inline, che Livewire rispetta. Nella pagina nuova tutte le
 | foto hanno il loro nome: quelle senza coppia entrano come il resto di <main>.
 | Lo stato inline finisce anche nella copia della pagina per il tasto Indietro: viene
 | tolto allo scambio (onSwap) e ricalcolato a ogni navigazione.
 |
 | ── Cuore dei preferiti ([data-favorite], x-ui.animal-card.favorite) ────────────────────
 | Al click il cuore fa un «pop» breve (scala con --ease-pop, --duration-slow ≤ 300 ms) se
 | sta per diventare preferito, un piccolo rimbalzo se torna vuoto. È solo un riscontro
 | visivo immediato: lo stato vero resta quello di aria-pressed, che arriva dal server
 | (Livewire) o dal componente Alpine. Listener delegato su document in fase di CATTURA:
 | legge aria-pressed prima dei listener del bottone (un x-on:click di Alpine lo
 | aggiornerebbe prima, e l'animazione andrebbe al contrario). Vale anche per i bottoni
 | aggiunti dopo (morph, wire:navigate) senza registrare nulla per bottone. Niente pop per
 | l'ospite (data-favorite-guest): il suo clic porta alla pagina di accesso, non salva.
 */

import '../../css/ui/motion.css';

let motionModule;

const loadMotion = () => (motionModule ??= import('motion/mini'));

const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/** Valore di un token CSS di app.css (custom property su :root). */
const token = (name) => getComputedStyle(document.documentElement).getPropertyValue(name).trim();

/** Durata di un token (`300ms`, `0.3s`) in secondi, come la vuole Motion. */
const seconds = (value, fallback) => {
    const match = /^([\d.]+)(ms|s)$/.exec(value);

    return match ? Number(match[1]) / (match[2] === 'ms' ? 1000 : 1) : fallback;
};

/** Curva di un token `cubic-bezier(a, b, c, d)` come array di Motion. */
const bezier = (value, fallback) => {
    const match = /^cubic-bezier\(([^)]+)\)$/.exec(value);
    const points = match ? match[1].split(',').map(Number) : [];

    return points.length === 4 && points.every(Number.isFinite) ? points : fallback;
};

// ── x-reveal ──────────────────────────────────────────────────────────────────────────────

const STATE = 'data-reveal-state';
const MAX_ORDER = 4;

let revealObserver;

/** Fine del reveal: tolti stato e ordine, l'elemento torna ai suoi stili (hover, transizioni). */
const settle = (element) => {
    element.removeAttribute(STATE);
    element.style.removeProperty('--reveal-order');
};

const show = (element, order) => {
    revealObserver.unobserve(element);
    element.style.setProperty('--reveal-order', String(order));
    element.setAttribute(STATE, 'shown');

    const done = () => {
        clearTimeout(timer);
        element.removeEventListener('transitionend', onEnd);
        if (element.getAttribute(STATE) === 'shown') {
            settle(element);
        }
    };
    const onEnd = (event) => {
        if (event.target === element && event.propertyName === 'opacity') {
            done();
        }
    };
    // Riserva: transitionend non arriva se l'elemento sparisce o la transizione non parte.
    const timer = setTimeout(done, 1500);

    element.addEventListener('transitionend', onEnd);
};

const onIntersect = (entries) => {
    const entering = [];

    for (const entry of entries) {
        const element = entry.target;
        const state = element.getAttribute(STATE);

        if (state === 'init') {
            // Prima notifica: in vista (sopra la piega) o motion ridotto → resta visibile.
            if (entry.isIntersecting || reducedMotion()) {
                revealObserver.unobserve(element);
                settle(element);
            } else {
                element.setAttribute(STATE, 'pending');
            }
        } else if (state === 'pending') {
            if (entry.isIntersecting) {
                entering.push(entry);
            }
        } else {
            // Stato tolto da un morph di Livewire: l'elemento è già visibile.
            revealObserver.unobserve(element);
        }
    }

    // In ordine di lettura (dall'alto, poi da sinistra), per la sequenza di una riga di card.
    entering
        .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top || a.boundingClientRect.left - b.boundingClientRect.left)
        .forEach((entry, index) => show(entry.target, reducedMotion() ? 0 : Math.min(index, MAX_ORDER)));
};

/** Font pronti (o subito, senza Font Loading API); mai più di 1 s di attesa. */
const fontsReady = () =>
    Promise.race([document.fonts?.ready ?? Promise.resolve(), new Promise((resolve) => setTimeout(resolve, 1000))]);

/** Reveal spento dalla configurazione (config/arkvel.php → reveal, scritto su <html>). */
const revealOff = () => document.documentElement.dataset.reveal === 'off';

const reveal = (element, cleanup) => {
    if (reducedMotion() || revealOff() || typeof IntersectionObserver !== 'function') {
        return;
    }

    let active = true;

    element.setAttribute(STATE, 'init');

    // La decisione «sopra o sotto la piega» si prende a font caricati: lo scambio del font
    // cambia le altezze, e un blocco giudicato sotto la piega potrebbe salire in vista da
    // solo e comparire con il reveal senza che nessuno abbia scorso la pagina.
    fontsReady().then(() => {
        if (active && element.getAttribute(STATE) === 'init') {
            revealObserver ??= new IntersectionObserver(onIntersect, { threshold: 0 });
            revealObserver.observe(element);
        }
    });

    cleanup(() => {
        active = false;
        revealObserver?.unobserve(element);
        settle(element);
    });
};

document.addEventListener('alpine:init', () => {
    window.Alpine.directive('reveal', (element, _directive, { cleanup }) => reveal(element, cleanup));
    // Anche fuori da x-data / wire:id (es. pagine Blade statiche).
    window.Alpine.addInitSelector(() => `[${window.Alpine.prefixed('reveal')}]`);
});

// ── Foto card → scheda ───────────────────────────────────────────────────────────────────

const PHOTO = '[data-photo-transition]';
const PHOTO_OFF = 'data-photo-transition-off';

/** Toglie i nomi spenti da questo modulo (anche nella copia di una pagina ripristinata). */
const restorePhotos = () => {
    document.querySelectorAll(`[${PHOTO_OFF}]`).forEach((element) => {
        element.style.removeProperty('view-transition-name');
        element.removeAttribute(PHOTO_OFF);
    });
};

const samePath = (href, destination) => {
    try {
        return new URL(href, window.location.href).pathname === destination.pathname;
    } catch {
        return false;
    }
};

document.addEventListener('livewire:navigate', (event) => {
    const destination = event.detail?.url;

    restorePhotos();

    if (!(destination instanceof URL)) {
        return;
    }

    const photos = [...document.querySelectorAll(PHOTO)];
    const linked = photos.filter((element) => element.dataset.photoTransition && samePath(element.dataset.photoTransition, destination));
    const kept = new Set(linked.length > 0 ? linked : photos.filter((element) => !element.dataset.photoTransition));

    photos
        .filter((element) => !kept.has(element))
        .forEach((element) => {
            element.style.setProperty('view-transition-name', 'none');
            element.setAttribute(PHOTO_OFF, '');
        });
});

document.addEventListener('livewire:navigating', (event) => {
    event.detail?.onSwap?.(restorePhotos);
});

// ── Cuore dei preferiti ───────────────────────────────────────────────────────────────────

const FAVORITE = '[data-favorite]';

/** Precarica Motion quando un cuore sta per essere usato (puntatore sopra o focus). */
const preload = (event) => {
    if (event.target instanceof Element && event.target.closest(FAVORITE) && !reducedMotion()) {
        loadMotion().catch(() => {
            motionModule = undefined;
        });
    }
};

document.addEventListener('pointerover', preload, { passive: true });
document.addEventListener('focusin', preload);

// In cattura: prima del x-on:click di Alpine o del wire:click sul bottone (vedi l'intestazione).
const pop = (event) => {
    const button = event.target instanceof Element ? event.target.closest(FAVORITE) : null;
    const icon = button?.querySelector('svg');

    if (!icon || reducedMotion() || button.hasAttribute('data-favorite-guest')) {
        return;
    }

    // Lo stato al momento del click: false → sta per diventare preferito.
    const adding = button.getAttribute('aria-pressed') !== 'true';
    const duration = seconds(token('--duration-slow'), 0.3);

    loadMotion()
        .then(({ animate }) => {
            if (reducedMotion()) {
                return;
            }

            animate(
                icon,
                { scale: adding ? [0.6, 1] : [0.85, 1] },
                {
                    duration,
                    ease: adding ? bezier(token('--ease-pop'), [0.34, 1.56, 0.64, 1]) : bezier(token('--ease-out'), [0.22, 1, 0.36, 1]),
                },
            );
        })
        .catch(() => {
            // Solo decorativa: se il chunk non arriva, il toggle funziona lo stesso.
            motionModule = undefined;
        });
};

document.addEventListener('click', pop, { capture: true });
