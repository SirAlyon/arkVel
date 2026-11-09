<?php

declare(strict_types=1);

use App\Enums\Species;
use App\Models\Animal;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\Ui\BrowserScripts;

uses(RefreshDatabase::class);

/*
 | Mappe pubbliche (UI-7) nel browser: vista «Mappa» del catalogo e mappa della pagina
 | dell'ente. MapLibre scaricato solo dove serve, segnaposto e riquadro da tastiera,
 | cluster, accessibilità (axe) in chiaro e in scuro con la mappa caricata, messaggio e
 | solo elenco se lo stile non si carica, nessun overflow a 320 px.
 |
 | Le tile vere (tiles.openfreemap.org) non servono ai test: lo stile punta a uno stile
 | minimo servito dall'app di test (solo sfondo), oppure a un indirizzo irraggiungibile
 | per provare il fallback. Gira SOLO nel servizio `browser-tests`.
 */

const MAP_TEST_STYLE = '/_test/map-style.json';

beforeEach(function (): void {
    // Stile minimo: sfondo e due layer di testo su una sorgente vuota (nessuna tile né glifo
    // da scaricare), uno con un nome di luogo e uno con una sigla stradale.
    Route::get(MAP_TEST_STYLE, static fn () => response()->json([
        'version' => 8,
        'name' => 'test',
        'glyphs' => '/_test/glyphs/{fontstack}/{range}.pbf',
        'sources' => ['places' => ['type' => 'geojson', 'data' => ['type' => 'FeatureCollection', 'features' => []]]],
        'layers' => [
            ['id' => 'background', 'type' => 'background', 'paint' => ['background-color' => 'rgb(236, 234, 226)']],
            ['id' => 'place-label', 'type' => 'symbol', 'source' => 'places', 'layout' => ['text-field' => '{name:latin}']],
            ['id' => 'road-shield', 'type' => 'symbol', 'source' => 'places', 'layout' => ['text-field' => '{ref}']],
        ],
    ]));

    // Relativi alla pagina: l'host del server di test non è ancora noto in beforeEach.
    config([
        'services.maps.style_light' => MAP_TEST_STYLE,
        'services.maps.style_dark' => MAP_TEST_STYLE.'?dark=1',
    ]);
});

/** Ripete la condizione JS (ogni 50 ms, al massimo $timeout ms) e restituisce l'ultimo esito. */
function mapEventually(string $condition, int $timeout = 10000): string
{
    return 'function () { return new Promise((resolve) => { const end = Date.now() + '.$timeout.'; const check = () => { let ok = false; try { ok = Boolean('.$condition.'); } catch (error) { ok = false; } if (ok || Date.now() > end) { resolve(ok); } else { setTimeout(check, 50); } }; check(); }); }';
}

function mapState(string $state, string $scope = '[data-ui-map]'): string
{
    return mapEventually('document.querySelector('.json_encode($scope).')?.dataset.state === '.json_encode($state));
}

function mapLibraryLoaded(): string
{
    return 'performance.getEntriesByType("resource").some((entry) => /maplibre-gl/.test(entry.name))';
}

/** Una proprietà di un layer della mappa (metodi layoutProperty / paintProperty del componente Alpine). */
function mapLayer(string $method, string $layer, string $property): string
{
    return 'JSON.stringify(Alpine.$data(document.querySelector("[data-ui-map]")).'.$method.'('.json_encode($layer).', '.json_encode($property).'))';
}

/** Il riquadro è tutto dentro la cornice della mappa (al pixel). */
const MAP_POPUP_INSIDE = '(() => { const popup = document.querySelector(".maplibregl-popup")?.getBoundingClientRect(); const frame = document.querySelector("[data-map-frame]").getBoundingClientRect(); return Boolean(popup) && popup.left >= frame.left - 1 && popup.right <= frame.right + 1 && popup.top >= frame.top - 1 && popup.bottom <= frame.bottom + 1; })()';

/**
 * Due enti lontani (Roma, Milano). `$cluster`: `near` aggiunge un ente a ~4 km da Roma
 * (un gruppo che lo zoom separa), `same` un ente nella stessa cella di Roma (stessa
 * posizione approssimata: un gruppo che si può solo elencare).
 */
function seedMapOrganizations(?string $cluster = null): void
{
    $rows = [
        ['aurora', 'Rifugio Aurora', 'Roma', 'Lazio', 41.8902102, 12.4922309, 3],
        ['stella', 'Canile Stella', 'Milano', 'Lombardia', 45.4642035, 9.1899820, 2],
    ];

    if ($cluster === 'near') {
        $rows[] = ['tevere', 'Oasi del Tevere', 'Roma', 'Lazio', 41.9230, 12.4560, 1];
    }

    if ($cluster === 'same') {
        $rows[] = ['colosseo', 'Gattile Colosseo', 'Roma', 'Lazio', 41.8911, 12.4934, 1];
    }

    foreach ($rows as $row) {
        $organization = Organization::factory()->verified()->create([
            'slug' => $row[0],
            'name' => $row[1],
            'city' => $row[2],
            'region' => $row[3],
            'latitude' => $row[4],
            'longitude' => $row[5],
        ]);

        Animal::factory()->count($row[6])->available()->forOrganization($organization)->create(['species' => Species::Dog]);
    }
}

it('downloads MapLibre only when the map view opens, and switches view from the keyboard', function (): void {
    seedMapOrganizations();

    $page = visit(route('catalog'))->withLocale('it-IT');

    $page->assertPresent('[data-catalog-grid]')
        ->assertScript(mapLibraryLoaded(), false)
        ->assertAttribute('[data-view-switch-option="list"]', 'aria-current', 'true')
        ->keys('[data-view-switch-option="map"]', ['Enter'])
        ->assertScript(mapEventually('new URLSearchParams(location.search).get("view") === "map"'), true)
        ->assertScript(mapState('ready'), true)
        ->assertScript(mapLibraryLoaded(), true)
        ->assertAttribute('[data-view-switch-option="map"]', 'aria-current', 'true')
        ->assertScript('document.querySelectorAll("[data-map-list-item]").length', 2)
        ->assertScript(mapEventually('document.querySelectorAll("[data-map-marker]").length === 2'), true)
        ->assertNoJavaScriptErrors()
        ->keys('[data-view-switch-option="list"]', ['Enter'])
        ->assertScript(mapEventually('document.querySelector("[data-catalog-grid]") !== null && document.querySelector("[data-ui-map]") === null'), true)
        ->assertScript('location.search', '');
});

it('does not download MapLibre on pages without a map', function (): void {
    seedMapOrganizations();

    visit(route('home'))->withLocale('it-IT')
        ->assertScript(mapEventually('document.readyState === "complete"'), true)
        ->assertScript(mapLibraryLoaded(), false);
});

it('has no accessibility issues with the map loaded, in light and dark mode', function (): void {
    seedMapOrganizations();
    $url = route('catalog', ['view' => 'map']);

    visit($url)->withLocale('it-IT')
        ->assertScript(mapState('ready'), true)
        ->assertScript(mapEventually('document.querySelectorAll("[data-map-marker]").length === 2'), true)
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();

    visit($url)->inDarkMode()->withLocale('it-IT')
        ->assertScript('document.documentElement.classList.contains("dark")', true)
        ->assertScript(mapState('ready'), true)
        ->assertScript(mapEventually('document.querySelectorAll("[data-map-marker]").length === 2'), true)
        ->assertNoAccessibilityIssues();
});

it('gives the map a name and keyboard instructions, and opens the marker details from the keyboard', function (): void {
    seedMapOrganizations();

    $page = visit(route('catalog', ['view' => 'map']))->withLocale('it-IT');

    $page->assertScript(mapState('ready'), true)
        ->assertScript('document.querySelector("[data-ui-map] canvas").getAttribute("aria-label")', 'Mappa degli enti con animali che corrispondono alla ricerca')
        ->assertScript('document.querySelector("[data-ui-map] canvas").getAttribute("aria-describedby")', 'catalog-map-instructions')
        ->assertScript('document.querySelector("[data-ui-map] canvas").tabIndex', 0)
        ->assertScript(mapEventually('document.querySelector("[data-map-marker=aurora]") !== null'), true)
        ->assertAttribute('[data-map-marker="aurora"]', 'aria-label', 'Rifugio Aurora: 3 animali')
        ->assertScript(BrowserScripts::undersizedTargets('[data-ui-map]'), [])
        ->keys('[data-map-marker="aurora"]', ['Enter'])
        ->assertScript(mapEventually('document.querySelector("[data-map-popup=aurora]") !== null'), true)
        ->assertAttribute('[data-map-marker="aurora"]', 'aria-expanded', 'true')
        ->assertSeeIn('[data-map-popup="aurora"]', 'Roma, Lazio')
        ->assertSeeIn('[data-map-popup="aurora"]', '3 animali')
        ->assertScript('document.querySelector("[data-map-popup=aurora] a").getAttribute("href")', route('catalog', ['organization' => 'aurora']))
        ->assertScript(mapEventually('document.activeElement?.closest(".maplibregl-popup") !== null'), true)
        ->assertScript(BrowserScripts::undersizedTargets('.maplibregl-popup'), [])
        ->assertNoAccessibilityIssues()
        ->keys(':focus', ['Escape'])
        ->assertScript(mapEventually('document.querySelector("[data-map-popup]") === null'), true)
        ->assertScript(BrowserScripts::activeElement('data-map-marker'), 'aurora')
        ->assertAttribute('[data-map-marker="aurora"]', 'aria-expanded', 'false');
});

it('groups nearby organizations in a cluster that zooms in', function (): void {
    seedMapOrganizations('near');

    $page = visit(route('catalog', ['view' => 'map']))->withLocale('it-IT');

    $page->assertScript(mapState('ready'), true)
        ->assertScript(mapEventually('document.querySelector("[data-map-cluster]") !== null'), true)
        ->assertAttribute('[data-map-cluster]', 'aria-label', '4 animali in 2 enti vicini')
        ->assertSeeIn('[data-map-cluster]', '4')
        ->assertScript('document.querySelectorAll("[data-map-list-item]").length', 3)
        ->keys('[data-map-cluster]', ['Enter'])
        ->assertScript(mapEventually('document.querySelector("[data-map-cluster]") === null && document.querySelector("[data-map-marker=aurora]") !== null && document.querySelector("[data-map-marker=tevere]") !== null'), true)
        ->assertScript('document.activeElement?.tagName', 'CANVAS');
});

it('lists the organizations of the same approximate area in the cluster details', function (): void {
    seedMapOrganizations('same');

    $page = visit(route('catalog', ['view' => 'map']))->withLocale('it-IT');

    $page->assertScript(mapState('ready'), true)
        ->assertScript(mapEventually('document.querySelector("[data-map-cluster]") !== null'), true)
        ->keys('[data-map-cluster]', ['Enter'])
        ->assertScript(mapEventually('document.querySelector("[data-map-popup=cluster]") !== null'), true)
        ->assertAttribute('[data-map-cluster]', 'aria-expanded', 'true')
        ->assertSeeIn('[data-map-popup="cluster"]', 'Enti in questa zona')
        ->assertScript('[...document.querySelectorAll("[data-map-popup=cluster] li a")].map((link) => link.textContent).sort()', ['Gattile Colosseo', 'Rifugio Aurora'])
        ->assertScript(BrowserScripts::undersizedTargets('.maplibregl-popup', 24), [])
        ->assertNoAccessibilityIssues()
        ->keys(':focus', ['Escape'])
        ->assertScript(mapEventually('document.querySelector("[data-map-popup]") === null'), true)
        ->assertScript('document.activeElement?.hasAttribute("data-map-cluster")', true);
});

it('updates the markers when the filters change', function (): void {
    seedMapOrganizations();
    Animal::factory()->available()->forOrganization(Organization::query()->where('slug', 'stella')->firstOrFail())->create(['species' => Species::Cat, 'name' => 'Micia']);

    $page = visit(route('catalog', ['view' => 'map']))->withLocale('it-IT');

    $page->assertScript(mapState('ready'), true)
        ->assertScript(mapEventually('document.querySelectorAll("[data-map-marker]").length === 2'), true)
        ->click('#field-q')
        ->type('#field-q', 'Micia')
        ->assertScript(mapEventually('document.querySelectorAll("[data-map-list-item]").length === 1'), true)
        ->assertScript(mapEventually('document.querySelectorAll("[data-map-marker]").length === 1 && document.querySelector("[data-map-marker=stella]") !== null'), true)
        ->assertScript('document.querySelector("[data-ui-map]").dataset.state', 'ready')
        ->assertNoJavaScriptErrors();
});

it('shows a message and only the list when the map cannot load', function (): void {
    seedMapOrganizations();
    config(['services.maps.style_light' => 'http://127.0.0.1:9/unreachable-style.json']);

    $page = visit(route('catalog', ['view' => 'map']))->withLocale('it-IT');

    $page->assertScript(mapState('error'), true)
        ->assertSeeIn('[data-map-status]', 'Non è stato possibile caricare la mappa. Gli stessi enti sono nell’elenco.')
        ->assertScript('document.querySelector("[data-map-canvas]").checkVisibility()', false)
        // Le istruzioni restano nel layout (niente CLS), invisibili e fuori dall'albero accessibile.
        ->assertScript('document.querySelector("[data-map-instructions]").checkVisibility({ visibilityProperty: true })', false)
        ->assertAttribute('[data-map-instructions]', 'aria-hidden', 'true')
        ->assertScript('document.querySelectorAll("[data-map-list-item]").length', 2)
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
});

it('loads the organization map only near the viewport and draws the approximate area', function (): void {
    $organization = Organization::factory()->verified()->create(['name' => 'Rifugio Aurora', 'city' => 'Roma', 'region' => 'Lazio', 'latitude' => 41.8902102, 'longitude' => 12.4922309]);
    Animal::factory()->count(8)->available()->forOrganization($organization)->create();

    // Viewport desktop basso: la mappa è sotto la piega. (Con l'emulazione mobile di
    // Playwright la pagina headless non produce frame e IntersectionObserver non scatta.)
    $page = visit(route('organizations.show', $organization))->withLocale('it-IT')->resize(1280, 700);

    $page->assertScript('document.querySelector("[data-ui-map]").dataset.state', 'idle')
        ->assertScript(mapLibraryLoaded(), false)
        ->script('document.querySelector("[data-organization-map]").scrollIntoView()');

    $page->assertScript(mapState('ready'), true)
        ->assertScript(mapLibraryLoaded(), true)
        ->assertScript('document.querySelectorAll("[data-map-marker]").length', 0)
        ->assertScript('document.querySelector("[data-ui-map] canvas").getAttribute("aria-label")', 'Mappa della zona di Rifugio Aurora')
        // Cerchio: riempimento highlight tenue, bordo primary (colori diversi in chiaro).
        ->assertScript(mapLayer('paintProperty', 'ui-map-area-fill', 'fill-opacity'), '0.25')
        ->assertScript(mapLayer('paintProperty', 'ui-map-area-fill', 'fill-color').' !== '.mapLayer('paintProperty', 'ui-map-area-line', 'line-color'), true)
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();

    $dark = visit(route('organizations.show', $organization))->inDarkMode()->withLocale('it-IT');
    $dark->script('document.querySelector("[data-organization-map]").scrollIntoView()');
    $dark->assertScript(mapState('ready'), true)
        ->assertNoAccessibilityIssues();
});

it('follows the theme of the page with the map open', function (): void {
    seedMapOrganizations();

    $page = visit(route('catalog', ['view' => 'map']))->withLocale('it-IT');

    $page->assertScript(mapState('ready'), true)
        ->script('window.arkvelTheme.set("dark")');

    $page->assertScript(mapEventually('performance.getEntriesByType("resource").some((entry) => entry.name.includes("dark=1"))'), true)
        ->assertScript(mapState('ready'), true)
        ->assertScript(mapEventually('document.querySelectorAll("[data-map-marker]").length === 2'), true)
        // Il nuovo stile ha di nuovo le etichette nella lingua della pagina.
        ->assertScript(mapEventually(mapLayer('layoutProperty', 'place-label', 'text-field').'.includes("name:it")'), true)
        ->assertNoJavaScriptErrors();
});

it('falls back to the message when the style of the new theme cannot load', function (): void {
    seedMapOrganizations();
    config(['services.maps.style_dark' => 'http://127.0.0.1:9/unreachable-dark.json']);

    $page = visit(route('catalog', ['view' => 'map']))->withLocale('it-IT');

    $page->assertScript(mapState('ready'), true)
        ->script('window.arkvelTheme.set("dark")');

    $page->assertScript(mapState('error'), true)
        ->assertSeeIn('[data-map-status]', 'Non è stato possibile caricare la mappa.')
        ->assertScript('document.querySelectorAll("[data-map-list-item]").length', 2);
});

it('writes the place names in the language of the page', function (string $locale, string $field): void {
    seedMapOrganizations();

    visit(route('catalog', ['view' => 'map']))->withLocale($locale)
        ->assertScript(mapState('ready'), true)
        ->assertScript(mapLayer('layoutProperty', 'place-label', 'text-field'), json_encode(['coalesce', ['get', $field], ['get', 'name:latin'], ['get', 'name']]))
        // Una sigla stradale non è un nome: resta com'è.
        ->assertScript(mapLayer('layoutProperty', 'road-shield', 'text-field'), json_encode('{ref}'));
})->with([
    'italian' => ['it-IT', 'name:it'],
    'english' => ['en-US', 'name:en'],
]);

it('shows the whole marker details on a 320 px screen, above the zoom controls', function (string $marker): void {
    seedMapOrganizations();

    visit(route('catalog', ['view' => 'map']))->withLocale('it-IT')->resize(320, 800)
        ->assertScript(mapState('ready'), true)
        ->assertScript(mapEventually('document.querySelector("[data-map-marker='.$marker.']") !== null'), true)
        ->assertScript('(() => { document.querySelector("[data-map-frame]").scrollIntoView({ block: "center" }); return true; })()', true)
        ->keys('[data-map-marker="'.$marker.'"]', ['Enter'])
        ->assertScript(mapEventually('document.querySelector("[data-map-popup='.$marker.']") !== null'), true)
        ->assertScript(mapEventually(MAP_POPUP_INSIDE, 3000), true)
        ->assertScript('getComputedStyle(document.querySelector(".maplibregl-popup")).zIndex', '3')
        ->assertScript('document.querySelector(".maplibregl-popup").getBoundingClientRect().width <= document.querySelector("[data-map-frame]").getBoundingClientRect().width - 32 + 1', true)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
})->with(['aurora', 'stella']);

it('makes the marker details a named dialog with an explicit link that navigates like the site', function (): void {
    seedMapOrganizations();

    $page = visit(route('catalog', ['view' => 'map']))->withLocale('it-IT');

    $page->assertScript(mapState('ready'), true)
        ->assertScript(mapEventually('document.querySelector("[data-map-marker=aurora]") !== null'), true)
        ->keys('[data-map-marker="aurora"]', ['Enter'])
        ->assertScript(mapEventually('document.querySelector("[data-map-popup=aurora]") !== null'), true)
        ->assertAttribute('.maplibregl-popup', 'role', 'dialog')
        ->assertScript('document.getElementById(document.querySelector(".maplibregl-popup").getAttribute("aria-labelledby"))?.textContent', 'Rifugio Aurora')
        ->assertScript('document.querySelector("[data-map-popup=aurora] a").textContent', 'Vedi gli animali di Rifugio Aurora')
        ->assertScript('document.querySelector("[data-map-popup=aurora] a .sr-only").textContent', 'di Rifugio Aurora')
        // Una navigazione SPA tiene lo stato della finestra; un caricamento completo no.
        ->assertScript('(() => { window.__spa = true; return true; })()', true)
        ->click('[data-map-popup="aurora"] a')
        ->assertScript(mapEventually('new URLSearchParams(location.search).get("organization") === "aurora" && document.querySelector("[data-catalog-grid]") !== null'), true)
        ->assertScript('window.__spa === true', true);
});

it('closes the marker details with Esc from anywhere in the map', function (): void {
    seedMapOrganizations();

    visit(route('catalog', ['view' => 'map']))->withLocale('it-IT')
        ->assertScript(mapState('ready'), true)
        ->assertScript(mapEventually('document.querySelector("[data-map-marker=aurora]") !== null'), true)
        ->keys('[data-map-marker="aurora"]', ['Enter'])
        ->assertScript(mapEventually('document.querySelector("[data-map-popup=aurora]") !== null'), true)
        ->keys('[data-ui-map] canvas', ['Escape'])
        ->assertScript(mapEventually('document.querySelector("[data-map-popup]") === null'), true)
        ->assertScript(BrowserScripts::activeElement('data-map-marker'), 'aurora')
        ->keys('[data-map-marker="aurora"]', ['Enter'])
        ->assertScript(mapEventually('document.querySelector("[data-map-popup=aurora]") !== null'), true)
        ->keys('.maplibregl-ctrl-zoom-in', ['Escape'])
        ->assertScript(mapEventually('document.querySelector("[data-map-popup]") === null'), true)
        ->assertScript(BrowserScripts::activeElement('data-map-marker'), 'aurora');
});

it('closes the details and focuses the map when their marker joins a cluster', function (): void {
    seedMapOrganizations('near');

    $page = visit(route('catalog', ['view' => 'map']))->withLocale('it-IT');

    $page->assertScript(mapState('ready'), true)
        ->assertScript(mapEventually('document.querySelector("[data-map-cluster]") !== null'), true)
        ->keys('[data-map-cluster]', ['Enter'])
        ->assertScript(mapEventually('document.querySelector("[data-map-marker=aurora]") !== null && document.querySelector("[data-map-cluster]") === null', 5000), true)
        ->wait(0.5)
        ->keys('[data-map-marker="aurora"]', ['Enter'])
        ->assertScript(mapEventually('document.querySelector("[data-map-popup=aurora]") !== null'), true)
        // Il focus è nel riquadro: si torna sul segnaposto e si allontana la mappa (− tre volte).
        ->assertScript('(() => { document.querySelector("[data-map-marker=aurora]").focus(); return true; })()', true)
        ->keys('[data-map-marker="aurora"]', ['Minus', 'Minus', 'Minus'])
        ->assertScript(mapEventually('document.querySelector("[data-map-marker=aurora]") === null && document.querySelector("[data-map-cluster]") !== null', 5000), true)
        ->assertScript('document.querySelector("[data-map-popup]")', null)
        ->assertScript('document.activeElement?.tagName', 'CANVAS');
});

it('puts the markers in the tab order of the list, and only those in view', function (): void {
    seedMapOrganizations();

    $page = visit(route('catalog', ['view' => 'map']))->withLocale('it-IT');

    $page->assertScript(mapState('ready'), true)
        ->assertScript(mapEventually('document.querySelectorAll("[data-map-marker]:not([inert])").length === 2'), true)
        ->assertScript('[...document.querySelectorAll("[data-map-marker]")].map((marker) => marker.dataset.mapMarker)', ['stella', 'aurora'])
        ->assertScript('[...document.querySelectorAll("[data-map-list-item]")].map((item) => item.dataset.mapListItem)', ['stella', 'aurora'])
        // Lo zoom da tastiera (+, a mappa ferma) allontana Roma e Milano dal centro: i
        // segnaposto escono di vista e dall'ordine di Tab.
        ->keys('[data-ui-map] canvas', ['Equal'])->wait(0.5)
        ->keys('[data-ui-map] canvas', ['Equal'])->wait(0.5)
        ->keys('[data-ui-map] canvas', ['Equal'])->wait(0.5)
        ->keys('[data-ui-map] canvas', ['Equal'])
        ->assertScript(mapEventually('[...document.querySelectorAll("[data-map-marker]")].every((marker) => marker.inert)', 6000), true)
        ->assertNoJavaScriptErrors();
});

it('does not shift the layout while the map view loads', function (): void {
    seedMapOrganizations();

    $page = visit(route('catalog', ['view' => 'map']))->withLocale('it-IT')->resize(1280, 900);

    $page->assertScript(mapState('ready'), true)
        ->assertScript(mapEventually('document.querySelectorAll("[data-map-marker]").length === 2'), true)
        ->wait(0.5);

    $shift = (float) $page->script('new Promise((resolve) => { let total = 0; new PerformanceObserver((list) => { for (const entry of list.getEntries()) { if (!entry.hadRecentInput) { total += entry.value; } } }).observe({ type: "layout-shift", buffered: true }); setTimeout(() => resolve(Math.round(total * 10000) / 10000), 200); })');

    expect($shift)->toBeLessThan(0.01);
});

it('goes back from the map to the grid with the back button', function (): void {
    seedMapOrganizations();

    $page = visit(route('catalog'))->withLocale('it-IT');

    $page->assertPresent('[data-catalog-grid]')
        ->keys('[data-view-switch-option="map"]', ['Enter'])
        ->assertScript(mapState('ready'), true)
        ->back()
        ->assertScript(mapEventually('document.querySelector("[data-catalog-grid]") !== null && document.querySelector("[data-ui-map]") === null'), true)
        ->assertScript('location.search', '');
});

it('has no horizontal overflow at 320 px', function (): void {
    seedMapOrganizations();
    $organization = Organization::query()->where('slug', 'aurora')->firstOrFail();

    visit(route('catalog', ['view' => 'map']))->withLocale('it-IT')->resize(320, 800)
        ->assertScript(mapState('ready'), true)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertScript(BrowserScripts::undersizedTargets('[data-view-switch]'), []);

    visit(route('organizations.show', $organization))->withLocale('it-IT')->resize(320, 800)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true);
});

it('draws the map at the size of its frame (regression: zero-height container)', function (): void {
    // maplibre-gl.css rimetteva .maplibregl-map in position: relative: contenitore alto 0,
    // stato "ready" ma niente di visibile.
    seedMapOrganizations();

    $size = '(() => { const m = document.querySelector("[data-map-canvas]").getBoundingClientRect(); const f = document.querySelector("[data-map-frame]").getBoundingClientRect(); return m.height > 100 && Math.abs(m.height - f.height) < 4 && Math.abs(m.width - f.width) < 4; })()';

    visit(route('catalog', ['view' => 'map']))->withLocale('it-IT')
        ->assertScript(mapState('ready'), true)
        ->assertScript($size, true);
});
