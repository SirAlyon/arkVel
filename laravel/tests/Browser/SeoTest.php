<?php

declare(strict_types=1);

use App\Models\Animal;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Ui\BrowserScripts;

/*
 | SEO nel browser: breadcrumb (una riga, niente overflow a 320 px, target ≥ 24 px,
 | aria-current) e dati strutturati che seguono la pagina con wire:navigate.
 | Gira SOLO nel servizio `browser-tests`: `docker compose --profile testing run --rm browser-tests`.
 */

uses(RefreshDatabase::class);

const LD_JSON_SCRIPTS = 'document.querySelectorAll(\'script[type="application/ld+json"]\')';

function longNamedAnimal(): Animal
{
    $organization = Organization::factory()->verified()->create([
        'name' => 'Associazione Amici a Quattro Zampe della Valle del Serchio',
    ]);

    return Animal::factory()->available()->forOrganization($organization)->create([
        'name' => 'Bartolomeo Ferdinando detto Briciola il Magnifico',
    ]);
}

it('keeps a long breadcrumb on one line without horizontal scroll at 320 px', function (): void {
    $animal = longNamedAnimal();

    visit(route('animals.show', $animal))->withLocale('it-IT')->resize(320, 720)
        ->assertNoJavaScriptErrors()
        ->assertAttribute('[data-breadcrumb] [aria-current="page"]', 'title', $animal->name)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertScript('(() => { const ol = document.querySelector("[data-breadcrumb] ol"); return ol.scrollWidth <= ol.clientWidth + 1; })()', true)
        ->assertScript('(() => { const middle = (el) => { const box = el.getBoundingClientRect(); return box.top + box.height / 2; }; const items = [...document.querySelectorAll("[data-breadcrumb] li")]; return items.every((li) => Math.abs(middle(li) - middle(items[0])) < 1); })()', true)
        ->assertScript('document.querySelector("[data-breadcrumb] li:nth-child(2) a").getBoundingClientRect().width >= 48', true)
        ->assertScript(BrowserScripts::undersizedTargets('[data-breadcrumb]', 24), []);
});

it('keeps breadcrumb targets large enough on desktop', function (): void {
    visit(route('animals.show', longNamedAnimal()))->withLocale('it-IT')
        ->assertScript(BrowserScripts::undersizedTargets('[data-breadcrumb]', 24), [])
        ->assertScript('document.querySelector("[data-breadcrumb]").getAttribute("aria-label")', 'Percorso di navigazione');
});

it('navigates from the breadcrumb and swaps the structured data with the page', function (): void {
    $animal = longNamedAnimal();

    $page = visit(route('animals.show', $animal))->withLocale('it-IT')
        ->assertScript(LD_JSON_SCRIPTS.'.length', 1)
        ->assertScript('JSON.parse('.LD_JSON_SCRIPTS.'[0].textContent)["@graph"][0]["@type"]', 'ItemPage');

    $page->click('[data-breadcrumb] li:nth-child(2) a')
        ->assertPathIs('/animali')
        ->assertTitle('Catalogo — ArkVel')
        ->assertScript('document.querySelector(\'link[rel="canonical"]\').href', route('catalog'))
        ->assertScript(LD_JSON_SCRIPTS.'.length', 1)
        ->assertScript('JSON.parse('.LD_JSON_SCRIPTS.'[0].textContent)["@graph"][0]["@type"]', 'CollectionPage')
        ->assertNoJavaScriptErrors();
});

it('keeps the intermediate crumbs readable at 320 px and truncates the current page instead', function (): void {
    $organization = Organization::factory()->verified()->create([
        'name' => 'Associazione Amici a Quattro Zampe della Valle del Serchio',
    ]);

    $fits = '(() => { const span = document.querySelector("[data-breadcrumb] li:nth-child(%d) a span"); return span.scrollWidth <= span.clientWidth; })()';
    $current = 'document.querySelector("[data-breadcrumb] [aria-current=page]")';

    visit(route('organizations.show', $organization))->withLocale('it-IT')->resize(320, 720)
        ->assertNoJavaScriptErrors()
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertScript('(() => { const ol = document.querySelector("[data-breadcrumb] ol"); return ol.scrollWidth <= ol.clientWidth + 1; })()', true)
        // «Home» ed «Enti» interi; il nome dell'ente (pagina corrente) coi puntini.
        ->assertScript(sprintf($fits, 1), true)
        ->assertScript(sprintf($fits, 2), true)
        ->assertScript($current.'.scrollWidth > '.$current.'.clientWidth', true)
        ->assertAttribute('[data-breadcrumb] [aria-current="page"]', 'title', $organization->name)
        ->assertScript(BrowserScripts::undersizedTargets('[data-breadcrumb]', 24), []);

    // Landing di donazione: l'ente diventa una voce intermedia, coi puntini oltre il tetto in ch.
    visit(route('organizations.donate.thanks', $organization))->withLocale('it-IT')->resize(320, 720)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->assertScript('(() => { const ol = document.querySelector("[data-breadcrumb] ol"); return ol.scrollWidth <= ol.clientWidth + 1; })()', true)
        ->assertScript(sprintf($fits, 2), true)
        ->assertScript(sprintf($fits, 3), false)
        ->assertScript('document.querySelector("[data-breadcrumb] [aria-current=page]").getBoundingClientRect().width >= 40', true);
});
