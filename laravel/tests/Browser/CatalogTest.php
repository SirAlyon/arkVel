<?php

declare(strict_types=1);

use App\Enums\Sex;
use App\Enums\Species;
use App\Models\Animal;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Ui\BrowserScripts;

uses(RefreshDatabase::class);

/*
 | Catalogo pubblico (/animali, UI-6b) nel browser: accessibilità (axe) in chiaro e in
 | scuro, ricerca istantanea con la regione live, drawer dei filtri e chip da tastiera,
 | «Carica altri» e caricamento continuo, target ≥ 44 px, nessun overflow a 320 px.
 | Gira SOLO nel servizio `browser-tests`: `docker compose --profile testing run --rm browser-tests`.
 */

/** Ripete la condizione JS (ogni 25 ms, al massimo $timeout ms) e restituisce l'ultimo esito. */
function catalogEventually(string $condition, int $timeout = 6000): string
{
    return 'function () { return new Promise((resolve) => { const end = Date.now() + '.$timeout.'; const check = () => { let ok = false; try { ok = Boolean('.$condition.'); } catch (error) { ok = false; } if (ok || Date.now() > end) { resolve(ok); } else { setTimeout(check, 25); } }; check(); }); }';
}

function seedCatalog(int $extra = 0): void
{
    $organization = Organization::factory()->verified()->create(['name' => 'Rifugio Aurora', 'slug' => 'aurora', 'region' => 'Lazio', 'city' => 'Roma']);

    Animal::factory()->available()->forOrganization($organization)->create(['name' => 'Argo', 'species' => Species::Dog, 'sex' => Sex::Male, 'featured' => true]);
    Animal::factory()->available()->forOrganization($organization)->create(['name' => 'Briciola', 'species' => Species::Dog, 'sex' => Sex::Female, 'featured' => false]);
    Animal::factory()->available()->forOrganization($organization)->create(['name' => 'Micia', 'species' => Species::Cat, 'sex' => Sex::Female, 'featured' => false]);

    for ($number = 1; $number <= $extra; $number++) {
        Animal::factory()->available()->forOrganization($organization)->create(['name' => sprintf('Cucciolo%02d', $number), 'featured' => false]);
    }
}

/** Card animale nella griglia (esclusa la card finale verso gli enti). */
function catalogCards(): string
{
    return 'document.querySelectorAll("[data-catalog-grid] [data-animal-card]").length';
}

it('has no accessibility issues, with results, chips and filters, in light and dark mode', function (): void {
    seedCatalog();
    $url = route('catalog', ['species' => 'dog', 'q' => 'r']);

    visit($url)->withLocale('it-IT')
        ->assertNoJavaScriptErrors()
        ->assertScript('document.documentElement.dataset.themeForced ?? null', null)
        ->assertSee('Briciola')
        ->assertNoAccessibilityIssues();

    visit($url)->inDarkMode()->withLocale('it-IT')
        ->assertScript('document.documentElement.classList.contains("dark")', true)
        ->assertNoAccessibilityIssues();

    visit(route('catalog', ['q' => 'nessuno-si-chiama-cosi']))->inDarkMode()->withLocale('it-IT')
        ->assertPresent('[data-catalog-empty="filtered"]')
        ->assertNoAccessibilityIssues();
});

it('updates only the results while typing and announces them in the live region', function (): void {
    seedCatalog();

    $page = visit(route('catalog'))->withLocale('it-IT');

    $page->assertSeeIn('[data-catalog-status]', '3 animali trovati')
        ->assertAttribute('[data-catalog-status]', 'role', 'status')
        ->click('#field-q')
        ->type('#field-q', 'Micia')
        ->assertScript(catalogEventually('document.querySelector("[data-catalog-status]").textContent.trim() === "1 animale trovato"'), true)
        ->assertScript(catalogEventually('new URLSearchParams(location.search).get("q") === "Micia"'), true)
        ->assertScript(BrowserScripts::activeElement(), 'field-q')
        ->assertScript(catalogCards(), 1)
        ->assertNoJavaScriptErrors();
});

it('filters from the drawer with the keyboard and gives the focus back', function (): void {
    seedCatalog();

    $page = visit(route('catalog'))->withLocale('it-IT')->on()->mobile();

    $page->assertScript('document.querySelector("[data-catalog-sidebar]").checkVisibility()', false)
        ->keys('[data-catalog-filters-trigger]', ['Enter'])
        ->assertScript(catalogEventually('document.querySelector("dialog[data-drawer=catalog-filters]").open'), true)
        ->assertScript('document.querySelector("dialog[data-drawer=catalog-filters]").contains(document.activeElement)', true)
        ->assertScript(BrowserScripts::undersizedTargets('dialog[data-drawer=catalog-filters]'), [])
        ->keys('#drawer-species-all', ['ArrowDown'])
        ->assertScript(catalogEventually('document.querySelector("[data-catalog-status]").textContent.trim() === "2 animali trovati"'), true)
        ->assertScript(catalogEventually('new URLSearchParams(location.search).get("species") === "dog"'), true)
        ->assertScript('document.querySelector("dialog[data-drawer=catalog-filters]").open', true)
        ->assertSeeIn('[data-catalog-drawer-close]', 'Mostra 2 animali')
        ->assertNoAccessibilityIssues()
        ->keys(':focus', ['Escape'])
        ->assertScript(catalogEventually('!document.querySelector("dialog[data-drawer=catalog-filters]").open'), true)
        ->assertScript('document.activeElement.hasAttribute("data-catalog-filters-trigger")', true)
        ->assertScript('document.querySelector("[data-catalog-filters-trigger] [data-variant]").textContent.trim()', '1');
});

it('removes the active filter chips from the keyboard, moving the focus to what is left', function (): void {
    seedCatalog();

    $page = visit(route('catalog', ['species' => 'dog', 'sex' => 'female']))->withLocale('it-IT');

    $page->assertScript('document.querySelectorAll("[data-catalog-chip]").length', 2)
        ->assertAttribute('[data-catalog-chip="species"] button', 'aria-label', 'Rimuovi Specie: Cane')
        ->keys('[data-catalog-chip="species"] button', ['Enter'])
        ->assertScript(catalogEventually('document.querySelectorAll("[data-catalog-chip]").length === 1'), true)
        ->assertScript(catalogEventually('document.activeElement?.closest("[data-catalog-chip]")?.dataset.catalogChip === "sex"'), true)
        ->assertSeeIn('[data-catalog-status]', '2 animali trovati')
        ->keys('[data-catalog-chip="sex"] button', ['Enter'])
        ->assertScript(catalogEventually('document.querySelector("[data-catalog-chips]") === null'), true)
        ->assertScript(catalogEventually('document.activeElement?.id === "field-q"'), true)
        ->assertScript('location.search', '')
        ->assertSeeIn('[data-catalog-status]', '3 animali trovati');
});

it('filters from the sidebar on desktop', function (): void {
    seedCatalog();

    visit(route('catalog'))->withLocale('it-IT')->resize(1280, 900)
        ->assertScript('document.querySelector("[data-catalog-filters-trigger]").checkVisibility()', false)
        ->click('#sidebar-species-cat')
        ->assertScript(catalogEventually('document.querySelector("[data-catalog-status]").textContent.trim() === "1 animale trovato"'), true)
        ->assertSee('Micia')
        ->assertScript('document.querySelector("#drawer-species-cat").checked', true)
        ->assertNoJavaScriptErrors();
});

it('loads more animals with the button and while scrolling', function (): void {
    seedCatalog(extra: 40);

    $page = visit(route('catalog', ['sort' => 'name']))->withLocale('it-IT')->resize(1280, 900);

    $page->assertScript(catalogCards(), 12)
        ->assertAttribute('[data-catalog-load-more]', 'href', route('catalog', ['sort' => 'name', 'page' => 2]))
        ->click('[data-catalog-load-more]')
        ->assertScript(catalogEventually(catalogCards().' >= 24'), true)
        ->assertScript('location.search', '?sort=name')
        ->assertSeeIn('[data-catalog-status]', 'mostrati dal 1 al');

    // La sentinella in fondo alla griglia carica da sola la pagina successiva (può
    // averlo già fatto, se dopo il clic era entro il margine di 600 px).
    $page->script('document.querySelector("[data-catalog-sentinel]")?.scrollIntoView()');
    $page->assertScript(catalogEventually(catalogCards().' >= 36'), true)
        ->assertNoJavaScriptErrors();
});

it('keeps every control at least 44 px and has no horizontal overflow at 320 px', function (): void {
    seedCatalog(extra: 12);

    visit(route('catalog', ['species' => 'dog', 'q' => 'o', 'region' => 'Lazio']))->withLocale('it-IT')->resize(320, 800)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertScript(BrowserScripts::undersizedTargets('[data-catalog]'), []);

    visit(route('catalog'))->withLocale('it-IT')->resize(1280, 900)
        ->assertScript(BrowserScripts::undersizedTargets('[data-catalog]'), []);
});

it('moves between the pages with the shared pagination, on mobile and desktop', function (int $width): void {
    seedCatalog(extra: 40);

    $page = visit(route('catalog', ['sort' => 'name', 'page' => 2]))->withLocale('it-IT')->resize($width, 900);

    $page->assertNoJavaScriptErrors()
        ->assertAttribute('[data-catalog-pagination]', 'aria-label', 'Pagine del catalogo')
        ->assertAttribute('[data-pagination-page="2"]', 'aria-current', 'page')
        ->assertScript('document.querySelector("[data-pagination-previous]").getAttribute("rel")', 'prev')
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertScript(BrowserScripts::undersizedTargets('[data-catalog-pagination]'), []);

    // Arrivando alla paginazione la sentinella carica da sola la pagina 3: si aspetta che
    // finisca (la 3 si evidenzia come già mostrata, ma resta una sola pagina corrente: la 2),
    // poi «successiva» porta alla 4.
    $page->script('document.querySelector("[data-catalog-pagination]").scrollIntoView()');
    $page->assertScript(catalogEventually('document.querySelector("[data-pagination-summary]")?.textContent.includes("Pagine da 2 a 3 di 4")'), true)
        ->assertAttribute('[data-pagination-page="3"]', 'aria-label', 'Pagina 3, già mostrata')
        ->assertScript('document.querySelectorAll("[data-catalog-pagination] [aria-current=page]").length', 1)
        ->click('[data-pagination-next]')
        ->assertScript(catalogEventually('new URLSearchParams(location.search).get("page") === "4"'), true)
        ->assertScript(catalogEventually('document.querySelector("[data-pagination-page=\'4\']")?.getAttribute("aria-current") === "page"'), true)
        ->assertNoJavaScriptErrors();
})->with(['mobile' => 375, 'desktop' => 1280]);

it('drops a pending continuous load when the visitor follows a pagination link', function (): void {
    seedCatalog(extra: 40);

    $page = visit(route('catalog', ['sort' => 'name']))->withLocale('it-IT')->resize(1280, 900);

    // Raccoglie gli errori della pagina (la finestra resta la stessa con wire:navigate) e
    // rallenta di 1,5 s le risposte di Livewire, così «Carica altri» è ancora in volo
    // quando la navigazione verso pagina 3 scambia la pagina.
    $page->script(<<<'JS'
        window.__catalogErrors = [];
        window.addEventListener('error', (event) => window.__catalogErrors.push(String(event.message)));
        window.addEventListener('unhandledrejection', (event) => window.__catalogErrors.push(String(event.reason?.message ?? event.reason)));
        const originalFetch = window.fetch;
        window.fetch = (input, init) => {
            const update = init?.headers?.['X-Livewire'] === '1';

            return update
                ? new Promise((resolve, reject) => setTimeout(() => originalFetch(input, init).then(resolve, reject), 1500))
                : originalFetch(input, init);
        };
        JS);

    $page->click('[data-catalog-load-more]')
        ->assertScript('document.querySelector("[data-catalog-loading]").checkVisibility()', true)
        ->click('[data-pagination-page="3"]')
        ->assertScript(catalogEventually('new URLSearchParams(location.search).get("page") === "3"'), true)
        ->assertScript(catalogEventually('document.querySelector("[data-pagination-summary]")?.textContent.includes("Pagina 3 di 4")'), true)
        // La risposta rallentata arriva dopo lo scambio: non deve toccare la nuova pagina.
        ->assertScript('new Promise((resolve) => setTimeout(() => resolve(window.__catalogErrors), 2500))', [])
        ->assertScript(catalogCards().' >= 12', true)
        ->assertSeeIn('[data-catalog-status]', 'mostrati dal 25 al')
        ->assertNoJavaScriptErrors();
});
