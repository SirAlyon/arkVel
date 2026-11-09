<?php

declare(strict_types=1);

use App\Data\Catalog\CatalogQuery;
use App\Enums\Sex;
use App\Enums\Size;
use App\Enums\Species;
use App\Livewire\AnimalCatalog;
use App\Models\Animal;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
 | Pagina del catalogo (/animali, UI-6b): componente Livewire sottile sopra SearchCatalog.
 | Ricerca e filtri legati alla query string, faccette con i conteggi (driver collection →
 | DatabaseFacetCounter), chip, ordinamento, «Carica altri» e paginazione, stati vuoti,
 | lingue, gerarchia dei titoli e visibilità.
 */

uses(RefreshDatabase::class);

if (! function_exists('verifiedOrg')) {
    function verifiedOrg(): Organization
    {
        return Organization::factory()->verified()->create();
    }
}

/**
 * @param  array<string, mixed>  $attributes
 */
function catalogAnimal(Organization $organization, array $attributes = []): Animal
{
    return Animal::factory()->available()->forOrganization($organization)->create($attributes);
}

function catalogAnimals(int $count): void
{
    $organization = verifiedOrg();

    foreach (range(1, $count) as $number) {
        catalogAnimal($organization, ['name' => sprintf('Cucciolo%02d', $number)]);
    }
}

it('renders the catalog page with the theme and a visible search label', function () {
    $this->get(route('catalog'))
        ->assertOk()
        ->assertSeeLivewire(AnimalCatalog::class)
        ->assertDontSee('data-theme-forced', escape: false)
        ->assertSee('<label', escape: false)
        ->assertSee('for="field-q"', escape: false)
        ->assertSee('Cerca un animale')
        ->assertSee('role="search"', escape: false);
});

it('lists available animals of verified organizations', function () {
    catalogAnimal(verifiedOrg(), ['name' => 'Pongo']);

    Livewire::test(AnimalCatalog::class)->assertSee('Pongo')->assertSee('1 animale trovato');
});

it('finds an animal by name via search', function () {
    $org = verifiedOrg();
    catalogAnimal($org, ['name' => 'Cerbero']);
    catalogAnimal($org, ['name' => 'Fufi']);

    Livewire::test(AnimalCatalog::class)
        ->set('search', 'Cerbero')
        ->assertSee('Cerbero')
        ->assertDontSee('Fufi');
});

it('narrows results by species filter', function () {
    $org = verifiedOrg();
    catalogAnimal($org, ['name' => 'CaneUno', 'species' => Species::Dog]);
    catalogAnimal($org, ['name' => 'GattoUno', 'species' => Species::Cat]);

    Livewire::test(AnimalCatalog::class)
        ->set('species', Species::Dog->value)
        ->assertSee('CaneUno')
        ->assertDontSee('GattoUno');
});

it('narrows results by size filter', function () {
    $org = verifiedOrg();
    catalogAnimal($org, ['name' => 'Grosso', 'size' => Size::Large]);
    catalogAnimal($org, ['name' => 'Piccino', 'size' => Size::Small]);

    Livewire::test(AnimalCatalog::class)
        ->set('size', Size::Large->value)
        ->assertSee('Grosso')
        ->assertDontSee('Piccino');
});

it('narrows results by sex filter', function () {
    $org = verifiedOrg();
    catalogAnimal($org, ['name' => 'Maschietto', 'sex' => Sex::Male]);
    catalogAnimal($org, ['name' => 'Femminuccia', 'sex' => Sex::Female]);

    Livewire::test(AnimalCatalog::class)
        ->set('sex', Sex::Male->value)
        ->assertSee('Maschietto')
        ->assertDontSee('Femminuccia');
});

it('narrows results by region and organization', function () {
    $lazio = Organization::factory()->verified()->create(['name' => 'Rifugio Aurora', 'slug' => 'aurora', 'region' => 'Lazio']);
    $veneto = Organization::factory()->verified()->create(['name' => 'Canile Laguna', 'slug' => 'laguna', 'region' => 'Veneto']);
    catalogAnimal($lazio, ['name' => 'Romolo']);
    catalogAnimal($veneto, ['name' => 'Doge']);

    Livewire::test(AnimalCatalog::class)
        ->set('region', 'Veneto')
        ->assertSee('Doge')
        ->assertDontSee('Romolo')
        ->set('region', '')
        ->set('organization', 'aurora')
        ->assertSee('Romolo')
        ->assertDontSee('Doge');
});

it('never leaks draft animals or animals of unverified organizations', function () {
    $verified = verifiedOrg();
    $pending = Organization::factory()->pending()->create(['name' => 'Ente In Attesa', 'slug' => 'in-attesa']);

    catalogAnimal($verified, ['name' => 'VisibileOk']);
    Animal::factory()->draft()->forOrganization($verified)->create(['name' => 'BozzaLeak']);
    catalogAnimal($pending, ['name' => 'PendingLeak']);
    Animal::factory()->adopted()->forOrganization($verified)->create(['name' => 'AdottatoLeak']);

    Livewire::test(AnimalCatalog::class)
        ->assertSee('VisibileOk')
        ->assertDontSee('BozzaLeak')
        ->assertDontSee('PendingLeak')
        ->assertDontSee('AdottatoLeak')
        ->assertDontSee('Ente In Attesa')
        // Lo slug di un ente non verificato non filtra né rivela nulla.
        ->set('organization', 'in-attesa')
        ->assertSee('VisibileOk')
        ->assertDontSee('PendingLeak')
        ->assertDontSee('data-catalog-chip="organization"', escape: false);
});

it('shows the facet counts of the visible animals', function () {
    $org = verifiedOrg();
    catalogAnimal($org, ['species' => Species::Dog]);
    catalogAnimal($org, ['species' => Species::Dog]);
    catalogAnimal($org, ['species' => Species::Cat]);
    Animal::factory()->draft()->forOrganization($org)->create(['species' => Species::Cat]);

    Livewire::test(AnimalCatalog::class)
        ->assertSee('Cane (2)')
        ->assertSee('Gatto (1)')
        ->assertSee('Altro (0)')
        // Conteggi disgiuntivi: scelta una specie, le altre restano scopribili.
        ->set('species', 'dog')
        ->assertSee('Gatto (1)')
        ->assertSee('2 animali trovati');
});

it('reads the filters from the query string and keeps the links of the home and the SEO', function () {
    $org = verifiedOrg();
    catalogAnimal($org, ['name' => 'Argo', 'species' => Species::Dog]);
    catalogAnimal($org, ['name' => 'Micia', 'species' => Species::Cat]);

    $this->get(route('catalog', ['species' => 'dog']))->assertOk()->assertSee('Argo')->assertDontSee('Micia');
    $this->get(route('catalog', ['q' => 'Mic']))->assertOk()->assertSee('Micia')->assertDontSee('Argo');

    Livewire::withQueryParams(['q' => 'Argo', 'species' => 'dog', 'sort' => 'name'])
        ->test(AnimalCatalog::class)
        ->assertSet('search', 'Argo')
        ->assertSet('species', 'dog')
        ->assertSet('sort', 'name')
        ->assertSee('Argo');
});

it('ignores unknown filter values in the query string', function () {
    catalogAnimal(verifiedOrg(), ['name' => 'Argo']);

    $this->get(route('catalog', ['species' => 'drago', 'sort' => 'prezzo']))
        ->assertOk()
        ->assertSee('Argo')
        ->assertDontSee('data-catalog-chips', escape: false);
});

it('shows the active filters as removable chips and removes them one by one', function () {
    catalogAnimal(verifiedOrg(), ['name' => 'Argo', 'species' => Species::Dog, 'sex' => Sex::Male]);

    Livewire::withQueryParams(['q' => 'Argo', 'species' => 'dog', 'sex' => 'male'])
        ->test(AnimalCatalog::class)
        ->assertSeeHtml('data-catalog-chip="q"')
        ->assertSeeHtml('data-catalog-chip="species"')
        ->assertSee('Rimuovi Specie: Cane')
        ->call('removeFilter', 'species')
        ->assertSet('species', '')
        ->assertSet('search', 'Argo')
        ->assertDontSeeHtml('data-catalog-chip="species"')
        ->call('removeFilter', 'q')
        ->assertSet('search', '')
        ->call('removeFilter', 'bogus')
        ->assertSet('sex', 'male');
});

it('clears search and filters but keeps the sort order', function () {
    catalogAnimal(verifiedOrg(), ['species' => Species::Dog]);

    Livewire::withQueryParams(['q' => 'x', 'species' => 'dog', 'region' => 'Lazio', 'sort' => 'name'])
        ->test(AnimalCatalog::class)
        ->call('resetFilters')
        ->assertSet('search', '')
        ->assertSet('species', '')
        ->assertSet('region', '')
        ->assertSet('sort', 'name');
});

it('sorts the results', function () {
    $org = verifiedOrg();
    catalogAnimal($org, ['name' => 'Zorro', 'featured' => false]);
    catalogAnimal($org, ['name' => 'Ape', 'featured' => false]);

    Livewire::test(AnimalCatalog::class)
        ->set('sort', 'name')
        ->assertSeeInOrder(['Ape', 'Zorro'])
        ->assertSee('Nome (A–Z)');
});

it('loads more animals in the same view and starts again from one page on a new filter', function () {
    catalogAnimals(30);

    Livewire::test(AnimalCatalog::class)
        ->set('sort', 'name')
        ->assertSee('Cucciolo12')
        ->assertDontSee('Cucciolo13')
        ->assertSeeHtml('wire:intersect.once.margin.600px="loadMore"')
        ->assertSee('30 animali trovati, mostrati dal 1 al 12')
        ->call('loadMore')
        ->assertSet('pages', 2)
        ->assertSee('Cucciolo24')
        ->assertSee('mostrati dal 1 al 24')
        ->call('loadMore')
        ->assertSee('Cucciolo30')
        ->assertDontSeeHtml('data-catalog-load-more')
        ->assertSeeHtml('data-catalog-end')
        ->set('species', 'dog')
        ->assertSet('pages', 1);
});

it('never loads more than the maximum number of pages in one view', function () {
    $catalog = Livewire::test(AnimalCatalog::class);

    foreach (range(1, CatalogQuery::MAX_PAGES + 3) as $ignored) {
        $catalog->call('loadMore');
    }

    $catalog->assertSet('pages', CatalogQuery::MAX_PAGES);
});

it('offers real links to the other pages for crawlers and browsers without JavaScript', function () {
    catalogAnimals(30);

    $this->get(route('catalog', ['sort' => 'name']))->assertOk()
        ->assertSee('href="'.e(route('catalog', ['sort' => 'name', 'page' => 2])).'"', escape: false)
        ->assertSee('Pagina 1 di 3');

    $this->get(route('catalog', ['sort' => 'name', 'page' => 2]))->assertOk()
        ->assertSee('Cucciolo13')
        ->assertDontSee('Cucciolo12')
        ->assertSee('href="'.route('catalog', ['sort' => 'name']).'"', escape: false)
        ->assertSee('href="'.e(route('catalog', ['sort' => 'name', 'page' => 3])).'"', escape: false)
        ->assertSee('rel="prev"', escape: false);

    Livewire::withQueryParams(['page' => 3, 'sort' => 'name'])
        ->test(AnimalCatalog::class)
        ->assertSee('Cucciolo30')
        ->assertDontSeeHtml('data-catalog-sentinel')
        ->assertSeeHtml('data-catalog-end');
});

it('shows an empty state with a way back when nothing matches', function () {
    catalogAnimal(verifiedOrg(), ['name' => 'Rex']);

    Livewire::test(AnimalCatalog::class)
        ->set('search', 'zzz-nessuna-corrispondenza-zzz')
        ->assertSee('Nessun animale con questi filtri')
        ->assertSeeHtml('data-catalog-empty="filtered"')
        ->assertSee('Azzera i filtri')
        ->call('resetFilters')
        ->assertSee('Rex');
});

it('has dedicated empty states for an empty catalog and a page past the last one', function () {
    $this->get(route('catalog'))->assertOk()->assertSee('Il catalogo è vuoto, per ora');

    catalogAnimal(verifiedOrg());

    $this->get(route('catalog', ['page' => 7]))->assertOk()
        ->assertSee('Questa pagina non c’è')
        ->assertSee('href="'.route('catalog').'"', escape: false);
});

it('speaks English', function () {
    catalogAnimal(verifiedOrg(), ['name' => 'Argo', 'species' => Species::Dog]);

    $this->withSession(['locale' => 'en'])->get(route('catalog', ['species' => 'dog']))->assertOk()
        ->assertSee('Animals for adoption')
        ->assertSee('Search for an animal')
        ->assertSee('1 animal found')
        ->assertSee('Remove Species: Dog')
        ->assertSee('Sort by');
});

it('keeps a heading order without gaps: h1, then h2 for filters and cards', function () {
    catalogAnimals(3);

    $html = $this->get(route('catalog'))->assertOk()->getContent();
    preg_match('/<main\b.*<\/main>/s', $html, $main);
    preg_match_all('/<h([1-6])\b/', $main[0], $levels);

    $levels = array_map(intval(...), $levels[1]);

    expect($levels[0])->toBe(1)
        ->and(array_count_values($levels)[1])->toBe(1)
        ->and(max($levels))->toBe(2)
        ->and(preg_match_all('/<h2\b[^>]*>\s*<a\b[^>]*data-card-link/', $main[0]))->toBe(3);
});

it('no longer uses the legacy components and brand colors', function () {
    catalogAnimal(verifiedOrg());

    $html = $this->get(route('catalog'))->assertOk()->getContent();
    preg_match('/<div[^>]*data-catalog>.*/s', $html, $catalog);

    expect($catalog[0])->not->toContain('brand-')
        ->not->toContain('accent-')
        ->toContain('data-animal-card');
});

it('asks the database for the favorites of the grid once, also after loading more', function () {
    catalogAnimals(15);
    $user = User::factory()->create();
    $user->favorites()->attach(Animal::query()->where('name', 'Cucciolo02')->value('id'));

    DB::enableQueryLog();

    $html = (string) $this->actingAs($user)->get(route('catalog', ['sort' => 'name']))->assertOk()->getContent();

    $favoriteQueries = static fn (): array => array_filter(DB::getQueryLog(), static fn (array $query): bool => str_contains($query['query'], '"favorites"'));

    expect($favoriteQueries())->toHaveCount(1)
        ->and(preg_match_all('/aria-pressed="true"\s+aria-label="Salva Cucciolo02 nei preferiti"/', $html))->toBe(1)
        ->and(preg_match_all('/aria-pressed="false"\s+aria-label="Salva Cucciolo/', $html))->toBe(11);

    DB::flushQueryLog();

    Livewire::actingAs($user)->withQueryParams(['sort' => 'name'])->test(AnimalCatalog::class)->call('loadMore')->assertSee('Cucciolo15');

    // Mount e loadMore: una query di preferiti per render, nessuna per card.
    expect($favoriteQueries())->toHaveCount(2);
});

it('shows the shared card: age, public location of the organization and a single link', function () {
    $organization = Organization::factory()->verified()->create(['city' => 'Roma', 'region' => 'Lazio']);
    catalogAnimal($organization, ['name' => 'Argo', 'estimated_age_months' => 30, 'birthdate' => null]);

    $this->get(route('catalog'))->assertOk()
        ->assertSee('Roma, Lazio')
        ->assertSee('2 anni')
        ->assertSee('data-card-link', escape: false);
});

it('points the canonical of an explicit default order to the base catalog', function () {
    catalogAnimal(verifiedOrg());

    $this->get(route('catalog', ['sort' => 'relevance']))->assertOk()
        ->assertSee('<link rel="canonical" href="'.route('catalog').'">', escape: false)
        ->assertDontSee('<meta name="robots" content="noindex', escape: false);
});

it('unifies the pagination with the shared component', function () {
    catalogAnimals(40);

    $html = (string) $this->get(route('catalog', ['sort' => 'name', 'page' => 2]))->assertOk()->getContent();

    expect($html)
        ->toContain('data-pagination')
        ->toContain('aria-label="Pagine del catalogo"')
        ->toContain('Pagina 2 di 4')
        ->toMatch('/<a[^>]*href="'.preg_quote(e(route('catalog', ['sort' => 'name', 'page' => 4])), '/').'"[^>]*aria-label="Pagina 4"/s')
        ->toMatch('/aria-current="page"\s+aria-label="Pagina 2"/');

    Livewire::withQueryParams(['sort' => 'name'])->test(AnimalCatalog::class)
        ->call('loadMore')
        ->assertSee('Pagine da 1 a 2 di 4');
});
