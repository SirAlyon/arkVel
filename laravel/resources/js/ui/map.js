/*
 | x-ui · gruppo "map": dati Alpine della mappa (resources/views/components/ui/map.blade.php).
 |
 | Questo modulo è nel bundle di ogni pagina (resources/js/ui.js), quindi resta minimo:
 | MapLibre GL JS (BSD-3, §2 del piano UI), il suo worker e il suo CSS arrivano con import
 | dinamico solo quando una mappa sta per entrare in vista (IntersectionObserver).
 |
 | - Tile: stili vettoriali di OpenFreeMap (config/services.php → maps), uno per tema; al
 |   cambio di tema (classe `.dark` su <html>) la mappa cambia stile.
 | - Etichette nella lingua della pagina (<html lang>): a ogni stile caricato (anche al cambio
 |   di tema) i layer di testo con un nome usano name:<lingua>, poi name:latin, poi name.
 | - Segnaposto: <button> HTML (focus, nome accessibile, Invio/Spazio), raggruppati con il
 |   clustering della sorgente GeoJSON di MapLibre. Con Tab si raggiungono solo quelli dentro
 |   la parte visibile della mappa (gli altri sono `inert`), nell'ordine dell'elenco
 |   alternativo (lo stesso dei dati del server; un gruppo prende il posto del suo primo ente).
 | - Riquadro (popup): role="dialog" con il titolo come nome, largo al massimo 288 px o la
 |   mappa meno 32 px; aperto, la mappa si sposta quanto basta per mostrarlo intero (senza
 |   animazione con reduced-motion). Esc lo chiude da qualunque punto della mappa e
 |   restituisce il focus al segnaposto; se il segnaposto confluisce in un gruppo (zoom) il
 |   riquadro si chiude e il focus va alla mappa. I suoi link navigano come il resto del sito
 |   (Livewire.navigate).
 | - Area: un cerchio del raggio dato attorno alla posizione approssimata.
 | - Errori: se lo stile non si carica (rete, tile bloccate, WebGL assente), anche dopo un
 |   cambio di tema, la mappa sparisce e resta il messaggio nella regione live; i dati
 |   restano nell'elenco o nel testo vicino.
 | - Reduced-motion (letto a ogni movimento): spostamenti senza animazione (jumpTo).
 | - I dati arrivano dal server in `data-map-markers`: con Livewire il morph riscrive
 |   l'attributo e la mappa si aggiorna (MutationObserver), il canvas resta (wire:ignore).
 | - Le istanze di MapLibre stanno fuori dallo stato reattivo di Alpine (chiusura): i proxy
 |   reattivi su oggetti così grandi rallentano e rompono la libreria.
 */

let libraryPromise;

const loadLibrary = () =>
    (libraryPromise ??= Promise.all([
        import('maplibre-gl'),
        import('maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url'),
        import('maplibre-gl/dist/maplibre-gl.css'),
        import('../../css/ui/map.css'),
    ])
        .then(([library, worker]) => {
            library.setWorkerUrl(worker.default);

            return library;
        })
        .catch((error) => {
            // Un errore di rete non resta in cache: la prossima mappa riprova.
            libraryPromise = undefined;
            throw error;
        }));

const reducedMotion = () => window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const isDark = () => document.documentElement.classList.contains('dark');

/** Sostituisce i segnaposto `{nome}` dei testi tradotti. */
const format = (template, values) => String(template ?? '').replace(/\{(\w+)\}/g, (match, key) => values[key] ?? match);

const formatNumber = (value) => new Intl.NumberFormat(document.documentElement.lang || undefined).format(value);

/** Lingua delle etichette della mappa: quella della pagina (es. `it`, `en`). */
const labelLanguage = () => (document.documentElement.lang || 'en').split('-')[0].toLowerCase();

/** Un layer di testo che mostra un nome di luogo (non un numero civico o una sigla stradale). */
const showsName = (layer) => layer.type === 'symbol' && layer.layout?.['text-field'] !== undefined && /name/.test(JSON.stringify(layer.layout['text-field']));

/** Navigazione SPA di Livewire per un clic «normale» su un link (non nuova scheda). */
const navigate = (event) => {
    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || !window.Livewire?.navigate) {
        return;
    }

    event.preventDefault();
    window.Livewire.navigate(event.currentTarget.href);
};

/**
 | Colore di un token del design system (es. `--color-primary`) come rgb(), per i layer di
 | MapLibre: i token sono in OKLCH e la conversione la fa il canvas del browser.
 */
const tokenColor = (name) => {
    const value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    const context = document.createElement('canvas').getContext('2d', { willReadFrequently: true });

    if (!context || value === '') {
        return value;
    }

    context.fillStyle = value;
    context.fillRect(0, 0, 1, 1);
    const [red, green, blue] = context.getImageData(0, 0, 1, 1).data;

    return `rgb(${red}, ${green}, ${blue})`;
};

// Lo stesso valore di App\Support\Map\ApproximateLocation::METERS_PER_DEGREE.
const METERS_PER_DEGREE = 111_320;

/** Semiassi (in gradi) di un cerchio di `radius` metri alla latitudine `lat`. */
const circleSpan = ({ lat, radius }) => [radius / (METERS_PER_DEGREE * Math.cos((lat * Math.PI) / 180)), radius / METERS_PER_DEGREE];

const circle = (area, steps = 64) => {
    const [spanLng, spanLat] = circleSpan(area);
    const ring = Array.from({ length: steps + 1 }, (_, index) => {
        const angle = (index / steps) * 2 * Math.PI;

        return [area.lng + spanLng * Math.cos(angle), area.lat + spanLat * Math.sin(angle)];
    });

    return { type: 'Feature', properties: {}, geometry: { type: 'Polygon', coordinates: [ring] } };
};

const areaBounds = (area) => {
    const [spanLng, spanLat] = circleSpan(area);

    return [
        [area.lng - spanLng, area.lat - spanLat],
        [area.lng + spanLng, area.lat + spanLat],
    ];
};

const markerBounds = (markers) =>
    markers.reduce(
        ([[west, south], [east, north]], { lat, lng }) => [
            [Math.min(west, lng), Math.min(south, lat)],
            [Math.max(east, lng), Math.max(north, lat)],
        ],
        [
            [Infinity, Infinity],
            [-Infinity, -Infinity],
        ],
    );

// `order`: posizione nell'elenco alternativo (ordine di Tab dei segnaposto).
const collection = (markers) => ({
    type: 'FeatureCollection',
    features: markers.map((marker, order) => ({
        type: 'Feature',
        properties: { id: marker.id, count: marker.count, order },
        geometry: { type: 'Point', coordinates: [marker.lng, marker.lat] },
    })),
});

const MARKERS = 'ui-map-markers';
const AREA = 'ui-map-area';
const SINGLE_ZOOM = 11;
const MAX_FIT_ZOOM = 12;
// Oltre questo zoom la mappa non serve: le posizioni sono approssimate a ~2 km.
const MAX_ZOOM = 15;
// Se lo stile non arriva entro questo tempo, la mappa lascia il posto al messaggio.
const STYLE_TIMEOUT = 15_000;
// Riquadro: larghezza massima e margine dal bordo della mappa.
const POPUP_MAX_WIDTH = 288;
const POPUP_GUTTER = 16;

document.addEventListener('alpine:init', () => {
    window.Alpine.data('uiMap', (options = {}) => {
        // Fuori dallo stato reattivo (vedi l'intestazione).
        let library = null;
        let map = null;
        let styleUrl = null;
        let styled = false;
        let timer = null;
        let visibility = null;
        let markersWatcher = null;
        let themeWatcher = null;
        let popup = null;
        // Chiave (in `shown`) e bottone del segnaposto che ha aperto il riquadro.
        let popupOwner = null;
        let markers = [];
        const shown = new Map();

        return {
            state: 'idle',
            labels: options.labels ?? {},

            init() {
                markers = this.readMarkers();
                markersWatcher = new MutationObserver(() => this.update());
                markersWatcher.observe(this.$root, { attributes: true, attributeFilter: ['data-map-markers'] });

                // Esc chiude il riquadro da qualunque punto della mappa (canvas, segnaposto,
                // controlli, riquadro stesso).
                this.$root.addEventListener('keydown', (event) => {
                    if (event.key === 'Escape' && popup) {
                        event.stopPropagation();
                        this.closePopup(true);
                    }
                });

                if (!('IntersectionObserver' in window)) {
                    this.load();

                    return;
                }

                visibility = new IntersectionObserver(
                    (entries) => {
                        if (entries.some((entry) => entry.isIntersecting)) {
                            visibility.disconnect();
                            visibility = null;
                            this.load();
                        }
                    },
                    { rootMargin: '200px 0px' },
                );
                visibility.observe(this.$root);
            },

            destroy() {
                visibility?.disconnect();
                markersWatcher?.disconnect();
                themeWatcher?.disconnect();
                this.teardown();
            },

            readMarkers() {
                try {
                    const data = JSON.parse(this.$root.dataset.mapMarkers || '[]');

                    return data.filter((marker) => Number.isFinite(marker.lat) && Number.isFinite(marker.lng));
                } catch {
                    return [];
                }
            },

            async load() {
                if (this.state !== 'idle') {
                    return;
                }

                this.state = 'loading';

                try {
                    library = await loadLibrary();
                } catch {
                    this.fail();

                    return;
                }

                // La mappa può essere sparita nel frattempo (wire:navigate, morph di Livewire).
                if (!this.$root.isConnected) {
                    return;
                }

                styleUrl = this.styleUrl();

                try {
                    map = new library.Map({
                        container: this.$refs.canvas,
                        style: styleUrl,
                        ...this.initialCamera(),
                        maxZoom: MAX_ZOOM,
                        attributionControl: false,
                        cooperativeGestures: true,
                        dragRotate: false,
                        pitchWithRotate: false,
                        touchPitch: false,
                        fadeDuration: reducedMotion() ? 0 : 200,
                        locale: this.labels.ui ?? {},
                    });
                } catch {
                    this.fail();

                    return;
                }

                map.keyboard.disableRotation();
                map.touchZoomRotate.disableRotation();
                map.addControl(new library.NavigationControl({ showCompass: false }), 'top-right');

                if (options.instructions) {
                    map.getCanvas().setAttribute('aria-describedby', options.instructions);
                }

                map.on('style.load', () => this.decorate());
                // Spostata la mappa, i segnaposto fuori vista escono dall'ordine di Tab.
                map.on('moveend', () => this.syncFocusable());
                map.on('error', (event) => {
                    // Solo gli errori dello stile sono fatali; una tile mancante no.
                    if (!styled && !event.sourceId) {
                        this.fail();
                    }
                });
                // A ogni frame (come l'esempio dei cluster HTML di MapLibre): dopo uno zoom le
                // tile vecchie restano qualche istante, e con loro i cluster che non ci sono più.
                map.on('render', () => this.renderMarkers());

                this.watchStyle();

                themeWatcher = new MutationObserver(() => this.syncTheme());
                themeWatcher.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
            },

            /** Lo stile deve caricarsi entro STYLE_TIMEOUT, o la mappa lascia il posto al messaggio. */
            watchStyle() {
                window.clearTimeout(timer);
                timer = window.setTimeout(() => {
                    if (!styled) {
                        this.fail();
                    }
                }, STYLE_TIMEOUT);
            },

            styleUrl() {
                const styles = options.styles ?? {};

                return (isDark() ? styles.dark : styles.light) ?? styles.light;
            },

            initialCamera() {
                if (markers.length === 1) {
                    return { center: [markers[0].lng, markers[0].lat], zoom: SINGLE_ZOOM };
                }

                if (markers.length > 1) {
                    return { bounds: markerBounds(markers), fitBoundsOptions: { padding: 48, maxZoom: MAX_FIT_ZOOM } };
                }

                if (options.area) {
                    return { bounds: areaBounds(options.area), fitBoundsOptions: { padding: 32 } };
                }

                return {};
            },

            /** Sorgenti e layer nostri: dopo ogni stile caricato (anche al cambio di tema). */
            decorate() {
                styled = true;
                window.clearTimeout(timer);
                this.localizeLabels();

                if (options.area && !map.getSource(AREA)) {
                    // Riempimento giallo tenue e bordo primary: leggibile su tile chiare e scure
                    // (in scuro primary è a sua volta il giallo, più pieno del riempimento).
                    map.addSource(AREA, { type: 'geojson', data: circle(options.area) });
                    map.addLayer({ id: `${AREA}-fill`, type: 'fill', source: AREA, paint: { 'fill-color': tokenColor('--color-highlight'), 'fill-opacity': 0.25 } });
                    map.addLayer({ id: `${AREA}-line`, type: 'line', source: AREA, paint: { 'line-color': tokenColor('--color-primary'), 'line-width': 3 } });
                }

                if (!options.area && !map.getSource(MARKERS)) {
                    map.addSource(MARKERS, {
                        type: 'geojson',
                        data: collection(markers),
                        cluster: true,
                        clusterRadius: 56,
                        // Fino allo zoom massimo: enti nella stessa cella restano un gruppo
                        // (il riquadro li elenca) invece di sovrapporsi.
                        clusterMaxZoom: MAX_ZOOM,
                        clusterProperties: { animals: ['+', ['get', 'count']], order: ['min', ['get', 'order']] },
                    });
                    // Layer invisibile: serve solo a far calcolare i cluster alla sorgente,
                    // i segnaposto visibili sono i <button> HTML.
                    map.addLayer({ id: MARKERS, type: 'circle', source: MARKERS, paint: { 'circle-radius': 0, 'circle-opacity': 0, 'circle-stroke-width': 0 } });
                }

                this.state = 'ready';
            },

            /** Nomi dei luoghi nella lingua della pagina (vedi l'intestazione). */
            localizeLabels() {
                const language = labelLanguage();
                const field = ['coalesce', ['get', `name:${language}`], ['get', 'name:latin'], ['get', 'name']];

                for (const layer of map.getStyle()?.layers ?? []) {
                    if (showsName(layer)) {
                        map.setLayoutProperty(layer.id, 'text-field', field);
                    }
                }
            },

            /** Proprietà di layout o di stile di un layer dello stile corrente (test e debug). */
            layoutProperty(layerId, name) {
                return map?.getLayer(layerId) ? (map.getLayoutProperty(layerId, name) ?? null) : null;
            },

            paintProperty(layerId, name) {
                return map?.getLayer(layerId) ? (map.getPaintProperty(layerId, name) ?? null) : null;
            },

            syncTheme() {
                const next = this.styleUrl();

                if (!map || next === styleUrl) {
                    return;
                }

                styleUrl = next;
                this.closePopup(false);
                // Come al primo caricamento: un errore del nuovo stile porta al messaggio.
                styled = false;
                this.watchStyle();
                map.setStyle(next, { diff: false });
            },

            /** Nuovi dati dal server (morph di Livewire). */
            update() {
                markers = this.readMarkers();

                if (!map || !styled) {
                    return;
                }

                this.closePopup(false);
                this.clearMarkers();
                map.getSource(MARKERS)?.setData(collection(markers));

                if (markers.length === 1) {
                    this.move({ center: [markers[0].lng, markers[0].lat], zoom: SINGLE_ZOOM });
                } else if (markers.length > 1) {
                    const camera = map.cameraForBounds(markerBounds(markers), { padding: 48, maxZoom: MAX_FIT_ZOOM });

                    if (camera) {
                        this.move(camera);
                    }
                }
            },

            move(camera) {
                if (reducedMotion()) {
                    map.jumpTo(camera);
                } else {
                    map.easeTo({ ...camera, duration: 300 });
                }
            },

            /** Allinea i <button> dei segnaposto e dei cluster a quelli della sorgente. */
            renderMarkers() {
                if (!map || !map.getSource(MARKERS) || !map.isSourceLoaded(MARKERS)) {
                    return;
                }

                const visible = new Set();
                const byId = new Map(markers.map((marker) => [marker.id, marker]));
                let added = false;

                for (const feature of map.querySourceFeatures(MARKERS)) {
                    const properties = feature.properties ?? {};
                    const key = properties.cluster ? `cluster-${properties.cluster_id}` : `marker-${properties.id}`;

                    if (visible.has(key)) {
                        continue;
                    }

                    visible.add(key);

                    if (shown.has(key)) {
                        continue;
                    }

                    const data = byId.get(properties.id);
                    const element = properties.cluster ? this.clusterButton(properties, feature.geometry.coordinates, key) : data && this.markerButton(data, key);

                    if (element) {
                        const marker = new library.Marker({ element, anchor: 'center' }).setLngLat(feature.geometry.coordinates).addTo(map);
                        const order = Number(properties.order ?? 0);

                        this.placeInOrder(element, order);
                        shown.set(key, { marker, element, order });
                        added = true;
                    }
                }

                for (const [key, entry] of shown) {
                    if (!visible.has(key)) {
                        if (key === popupOwner?.key) {
                            this.ownerGone();
                        }

                        entry.marker.remove();
                        shown.delete(key);
                    }
                }

                if (added) {
                    this.syncFocusable();
                }
            },

            /**
             | Ordine di Tab = ordine dell'elenco: il nuovo segnaposto va prima del primo già
             | presente con un ordine maggiore. Si sposta solo il nuovo (mai uno con il focus).
             */
            placeInOrder(element, order) {
                const next = [...shown.values()].filter((entry) => entry.order > order).sort((a, b) => a.order - b.order)[0];

                if (next?.element.isConnected) {
                    next.element.parentNode.insertBefore(element, next.element);
                }
            },

            /** Solo i segnaposto dentro la parte visibile della mappa sono raggiungibili con Tab. */
            syncFocusable() {
                if (!map) {
                    return;
                }

                const canvas = map.getCanvas();
                const width = canvas.clientWidth;
                const height = canvas.clientHeight;

                for (const { marker, element } of shown.values()) {
                    const point = map.project(marker.getLngLat());
                    const inside = point.x >= 0 && point.x <= width && point.y >= 0 && point.y <= height;

                    // Mai togliere di mano il segnaposto con il focus.
                    if (inside || element !== document.activeElement) {
                        element.toggleAttribute('inert', !inside);
                    }
                }
            },

            clearMarkers() {
                shown.forEach((entry) => entry.marker.remove());
                shown.clear();
            },

            markerButton(data, key) {
                const button = document.createElement('button');

                button.type = 'button';
                button.className = 'ui-map-marker';
                button.dataset.mapMarker = data.id;
                button.textContent = formatNumber(data.count);
                button.setAttribute('aria-label', format(this.labels.marker, { title: data.title, count: data.countLabel }));
                button.setAttribute('aria-expanded', 'false');
                button.addEventListener('click', (event) => {
                    event.stopPropagation();
                    this.openPopup([data], { key, button }, [data.lng, data.lat], data.id);
                });

                return button;
            },

            clusterButton(properties, coordinates, key) {
                const button = document.createElement('button');

                button.type = 'button';
                button.className = 'ui-map-marker ui-map-cluster';
                button.dataset.mapCluster = String(properties.point_count);
                button.textContent = formatNumber(properties.animals);
                button.setAttribute(
                    'aria-label',
                    format(this.labels.cluster, { animals: formatNumber(properties.animals), organizations: formatNumber(properties.point_count) }),
                );
                button.setAttribute('aria-expanded', 'false');
                button.addEventListener('click', async (event) => {
                    event.stopPropagation();

                    const source = map.getSource(MARKERS);
                    let zoom = map.getZoom() + 2;

                    try {
                        zoom = await source.getClusterExpansionZoom(properties.cluster_id);
                    } catch {
                        // Zoom di riserva: due livelli in più.
                    }

                    if (zoom <= MAX_ZOOM) {
                        this.move({ center: coordinates, zoom });
                        // Il cluster sparisce: il focus torna alla mappa, da cui Tab raggiunge
                        // i segnaposto appena comparsi.
                        map.getCanvas().focus({ preventScroll: true });

                        return;
                    }

                    // Enti nella stessa zona approssimata: nessuno zoom li separa, il
                    // riquadro li elenca tutti.
                    const leaves = await source.getClusterLeaves(properties.cluster_id, properties.point_count, 0).catch(() => []);
                    const byId = new Map(markers.map((marker) => [marker.id, marker]));
                    const entries = leaves.map((leaf) => byId.get(leaf.properties?.id)).filter(Boolean);

                    if (entries.length > 0) {
                        this.openPopup(entries, { key, button }, coordinates, 'cluster');
                    }
                });

                return button;
            },

            /** Il riquadro: un ente (titolo, località, animali, link) o l'elenco di un gruppo. */
            openPopup(entries, owner, lngLat, key) {
                this.closePopup(false);

                const { button } = owner;
                const titleId = `${options.id ?? 'map'}-popup-title`;
                const content = document.createElement('div');
                const element = (tag, className, text) => {
                    const node = document.createElement(tag);

                    node.className = className;
                    node.textContent = text;

                    return node;
                };
                const link = (className, text, href) => {
                    const node = element('a', className, text);

                    node.href = href;
                    node.addEventListener('click', navigate);

                    return node;
                };

                content.className = 'ui-map-popup';
                content.dataset.mapPopup = key;

                if (entries.length === 1) {
                    const [data] = entries;
                    const title = element('p', 'ui-map-popup__title', data.title);
                    const action = link('ui-map-popup__link', this.labels.action, data.url);

                    // Il nome dell'ente nel nome del link («Vedi gli animali di …»), solo per
                    // gli screen reader: il testo visibile resta l'inizio del nome (WCAG 2.5.3).
                    action.append(' ', element('span', 'sr-only', format(this.labels.actionContext, { title: data.title })));
                    title.id = titleId;
                    content.append(
                        title,
                        ...(data.description ? [element('p', 'ui-map-popup__meta', data.description)] : []),
                        element('p', 'ui-map-popup__meta', data.countLabel),
                        action,
                    );
                } else {
                    const title = element('p', 'ui-map-popup__title', this.labels.clusterTitle);
                    const list = document.createElement('ul');

                    title.id = titleId;
                    list.className = 'ui-map-popup__list';
                    list.setAttribute('role', 'list');
                    entries.forEach((data) => {
                        const item = document.createElement('li');

                        item.append(
                            link('ui-map-popup__item-link', data.title, data.url),
                            element('p', 'ui-map-popup__meta', [data.description, data.countLabel].filter(Boolean).join(' · ')),
                        );
                        list.append(item);
                    });
                    content.append(title, list);
                }

                const opened = new library.Popup({ offset: 30, maxWidth: `${this.popupWidth()}px`, focusAfterOpen: true, className: 'ui-map-popup-frame' })
                    .setLngLat(lngLat)
                    .setDOMContent(content)
                    .addTo(map);
                const frame = opened.getElement();

                frame.setAttribute('role', 'dialog');
                frame.setAttribute('aria-labelledby', titleId);

                opened.on('close', () => {
                    button.setAttribute('aria-expanded', 'false');

                    // Chiuso con il suo bottone (il focus era dentro e ora è sul body): torna
                    // al segnaposto. Un clic sulla mappa invece lascia il focus dov'è.
                    const active = document.activeElement;

                    if (button.isConnected && (active === null || active === document.body || frame.contains(active))) {
                        button.focus({ preventScroll: true });
                    }

                    if (popup === opened) {
                        popup = null;
                        popupOwner = null;
                    }
                });

                button.setAttribute('aria-expanded', 'true');
                popup = opened;
                popupOwner = owner;
                this.revealPopup(frame);
            },

            /** Larghezza massima del riquadro: 288 px, o la mappa meno i margini se è più stretta. */
            popupWidth() {
                return Math.max(160, Math.min(POPUP_MAX_WIDTH, this.$refs.canvas.clientWidth - 2 * POPUP_GUTTER));
            },

            /** Sposta la mappa quanto basta perché il riquadro sia tutto dentro la cornice. */
            revealPopup(frame) {
                const bounds = map.getContainer().getBoundingClientRect();
                const box = frame.getBoundingClientRect();
                const margin = POPUP_GUTTER / 2;
                const shift = (start, end, min, max) => {
                    if (start < min + margin) {
                        return start - min - margin;
                    }

                    return end > max - margin ? end - max + margin : 0;
                };
                const dx = shift(box.left, box.right, bounds.left, bounds.right);
                const dy = shift(box.top, box.bottom, bounds.top, bounds.bottom);

                if (dx !== 0 || dy !== 0) {
                    map.panBy([dx, dy], reducedMotion() ? { animate: false } : { duration: 300 });
                }
            },

            closePopup(restoreFocus) {
                const current = popup;
                const owner = popupOwner;

                if (!current) {
                    return;
                }

                popup = null;
                popupOwner = null;
                current.remove();

                if (restoreFocus) {
                    (owner?.button.isConnected ? owner.button : map?.getCanvas())?.focus({ preventScroll: true });
                }
            },

            /** Il segnaposto del riquadro aperto non c'è più (confluito in un gruppo). */
            ownerGone() {
                const active = document.activeElement;
                const hadFocus = popup?.getElement()?.contains(active) || popupOwner?.button === active;

                this.closePopup(false);

                if (hadFocus) {
                    map.getCanvas().focus({ preventScroll: true });
                }
            },

            fail() {
                if (this.state === 'error') {
                    return;
                }

                this.state = 'error';
                this.teardown();
            },

            teardown() {
                window.clearTimeout(timer);
                popup?.remove();
                popup = null;
                popupOwner = null;
                this.clearMarkers();
                map?.remove();
                map = null;
                styled = false;
            },
        };
    });
});
