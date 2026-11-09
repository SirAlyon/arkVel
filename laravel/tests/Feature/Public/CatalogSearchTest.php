<?php

declare(strict_types=1);

use App\Actions\Catalog\SearchCatalog;
use App\Data\Catalog\CatalogQuery;
use App\Enums\CatalogFacet;
use App\Enums\CatalogSort;
use App\Enums\Sex;
use App\Enums\Size;
use App\Enums\Species;
use App\Models\Animal;
use App\Models\Organization;
use App\Support\Catalog\ActiveCatalogFilter;
use App\Support\Catalog\CatalogFacetGroup;
use App\Support\Catalog\CatalogFacetOption;
use App\Support\Catalog\CatalogResults;
use App\Support\Catalog\ResolveCatalogFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

/*
 | Ricerca del catalogo (Action SearchCatalog e risoluzione dei filtri) sul driver Scout
 | `collection` dei test: filtri validi, visibilità, pagine caricate, ordinamenti,
 | faccette con i conteggi e chip dei filtri attivi.
 */

uses(RefreshDatabase::class);

function searchCatalog(CatalogQuery $query = new CatalogQuery): CatalogResults
{
    return app(SearchCatalog::class)($query);
}

/**
 * Animali visibili «A01», «A02»… in ordine di nome, per controllare le pagine.
 */
function namedAnimals(Organization $organization, int $count): void
{
    foreach (range(1, $count) as $number) {
        Animal::factory()->available()->forOrganization($organization)->create(['name' => sprintf('A%02d', $number)]);
    }
}

/**
 * @return list<string>
 */
function resultNames(CatalogResults $results): array
{
    return $results->animals->pluck('name')->values()->all();
}

function facetGroup(CatalogResults $results, CatalogFacet $facet): CatalogFacetGroup
{
    return collect($results->facets)->first(fn (CatalogFacetGroup $group): bool => $group->facet === $facet);
}

describe('filters', function (): void {
    it('drops unknown values instead of searching for them', function (): void {
        $filters = app(ResolveCatalogFilters::class)(new CatalogQuery(
            search: '  rex  ',
            species: 'dragon',
            sex: 'x',
            size: 'huge',
            region: 'Atlantide',
            organization: 'nessuno',
        ));

        expect($filters->search)->toBe('rex')
            ->and($filters->hasFacetFilters())->toBeFalse();
    });

    it('resolves the organization and the region only among verified organizations', function (): void {
        $verified = Organization::factory()->verified()->create(['slug' => 'rifugio-aurora', 'region' => 'Lazio']);
        Organization::factory()->verified()->create(['region' => 'Lazio']);
        Organization::factory()->pending()->create(['slug' => 'in-attesa', 'region' => 'Molise']);
        Organization::factory()->suspended()->create(['region' => 'Lazio']);

        $resolve = app(ResolveCatalogFilters::class);

        expect($resolve(new CatalogQuery(organization: 'rifugio-aurora'))->organization?->is($verified))->toBeTrue()
            ->and($resolve(new CatalogQuery(organization: 'in-attesa'))->organization)->toBeNull()
            ->and($resolve(new CatalogQuery(region: 'Molise'))->region)->toBeNull()
            ->and($resolve(new CatalogQuery(region: 'Lazio'))->regionOrganizationIds)->toHaveCount(2);
    });

    it('caps the free search', function (): void {
        $filters = app(ResolveCatalogFilters::class)(new CatalogQuery(search: str_repeat('a', 300)));

        expect(mb_strlen($filters->search))->toBe(ResolveCatalogFilters::MAX_SEARCH_LENGTH);
    });
});

describe('results', function (): void {
    it('returns only publicly visible animals, with photos and organization loaded', function (): void {
        $verified = Organization::factory()->verified()->create();
        $pending = Organization::factory()->pending()->create();

        Animal::factory()->available()->forOrganization($verified)->create(['name' => 'Visibile']);
        Animal::factory()->draft()->forOrganization($verified)->create(['name' => 'Bozza']);
        Animal::factory()->adopted()->forOrganization($verified)->create(['name' => 'Adottato']);
        Animal::factory()->available()->forOrganization($pending)->create(['name' => 'EnteInAttesa']);

        $results = searchCatalog();

        expect(resultNames($results))->toBe(['Visibile'])
            ->and($results->total)->toBe(1)
            ->and($results->animals->first()->relationLoaded('media'))->toBeTrue()
            ->and($results->animals->first()->relationLoaded('organization'))->toBeTrue();
    });

    it('filters by region and organization', function (): void {
        $lazio = Organization::factory()->verified()->create(['slug' => 'aurora', 'region' => 'Lazio']);
        $veneto = Organization::factory()->verified()->create(['slug' => 'laguna', 'region' => 'Veneto']);
        Animal::factory()->available()->forOrganization($lazio)->create(['name' => 'Romolo']);
        Animal::factory()->available()->forOrganization($veneto)->create(['name' => 'Doge']);

        expect(resultNames(searchCatalog(new CatalogQuery(region: 'Veneto'))))->toBe(['Doge'])
            ->and(resultNames(searchCatalog(new CatalogQuery(organization: 'aurora'))))->toBe(['Romolo']);
    });

    it('returns nothing for an organization outside the chosen region, without leaking counts', function (): void {
        Organization::factory()->verified()->create(['slug' => 'aurora', 'region' => 'Lazio']);
        $veneto = Organization::factory()->verified()->create(['region' => 'Veneto']);
        Animal::factory()->available()->forOrganization($veneto)->create();

        $results = searchCatalog(new CatalogQuery(region: 'Veneto', organization: 'aurora'));

        expect($results->total)->toBe(0)
            ->and($results->isEmpty())->toBeTrue()
            ->and(collect(facetGroup($results, CatalogFacet::Species)->options)->sum('count'))->toBe(0);
    });

    it('loads one page or several consecutive pages', function (): void {
        namedAnimals(Organization::factory()->verified()->create(), 40);

        $first = searchCatalog(new CatalogQuery(sort: 'name'));
        $twoPages = searchCatalog(new CatalogQuery(sort: 'name', pages: 2));
        $unaligned = searchCatalog(new CatalogQuery(sort: 'name', page: 2, pages: 2));
        $aligned = searchCatalog(new CatalogQuery(sort: 'name', page: 3, pages: 2));

        expect(resultNames($first))->toBe(array_map(fn (int $n): string => sprintf('A%02d', $n), range(1, 12)))
            ->and($first->total)->toBe(40)
            ->and($first->lastLoadedPage)->toBe(1)
            ->and(resultNames($twoPages))->toBe(array_map(fn (int $n): string => sprintf('A%02d', $n), range(1, 24)))
            ->and($twoPages->lastLoadedPage)->toBe(2)
            ->and(resultNames($unaligned))->toBe(array_map(fn (int $n): string => sprintf('A%02d', $n), range(13, 36)))
            ->and($unaligned->lastLoadedPage)->toBe(3)
            ->and(resultNames($aligned))->toBe(array_map(fn (int $n): string => sprintf('A%02d', $n), range(25, 40)))
            ->and($aligned->lastLoadedPage)->toBe(4)
            ->and($aligned->hasMore())->toBeFalse();
    });

    it('stops at the last page and keeps the requested page when past it', function (): void {
        namedAnimals(Organization::factory()->verified()->create(), 14);

        $tooMany = searchCatalog(new CatalogQuery(page: 2, pages: 5));
        $past = searchCatalog(new CatalogQuery(page: 9));

        expect($tooMany->animals)->toHaveCount(2)
            ->and($tooMany->lastLoadedPage)->toBe(2)
            ->and($past->isEmpty())->toBeTrue()
            ->and($past->firstPage)->toBe(9)
            ->and($past->lastLoadedPage)->toBe(9)
            ->and($past->lastPage())->toBe(2);
    });

    it('sorts by arrival, waiting time, name or featured first', function (): void {
        $organization = Organization::factory()->verified()->create();
        Animal::factory()->available()->forOrganization($organization)->create(['name' => 'Bruno', 'featured' => false, 'created_at' => Carbon::parse('2026-01-01')]);
        Animal::factory()->available()->forOrganization($organization)->create(['name' => 'Ciro', 'featured' => true, 'created_at' => Carbon::parse('2026-02-01')]);
        Animal::factory()->available()->forOrganization($organization)->create(['name' => 'Asia', 'featured' => false, 'created_at' => Carbon::parse('2026-03-01')]);

        expect(resultNames(searchCatalog(new CatalogQuery(sort: 'newest'))))->toBe(['Asia', 'Ciro', 'Bruno'])
            ->and(resultNames(searchCatalog(new CatalogQuery(sort: 'waiting'))))->toBe(['Bruno', 'Ciro', 'Asia'])
            ->and(resultNames(searchCatalog(new CatalogQuery(sort: 'name'))))->toBe(['Asia', 'Bruno', 'Ciro'])
            ->and(resultNames(searchCatalog()))->toBe(['Ciro', 'Asia', 'Bruno'])
            ->and(searchCatalog(new CatalogQuery(sort: 'bogus'))->sort)->toBe(CatalogSort::Relevance);
    });
});

describe('facets and chips', function (): void {
    it('lists every enum value with its count and only organizations and regions with animals', function (): void {
        $aurora = Organization::factory()->verified()->create(['name' => 'Rifugio Aurora', 'slug' => 'aurora', 'region' => 'Lazio']);
        Organization::factory()->verified()->create(['name' => 'Ente Vuoto', 'region' => 'Umbria']);
        Animal::factory()->available()->forOrganization($aurora)->create(['species' => Species::Dog, 'sex' => Sex::Male, 'size' => Size::Small]);
        Animal::factory()->available()->forOrganization($aurora)->create(['species' => Species::Dog, 'sex' => Sex::Female, 'size' => Size::Small]);

        $results = searchCatalog(new CatalogQuery(species: 'dog'));

        expect(facetGroup($results, CatalogFacet::Species)->options)->toEqual([
            new CatalogFacetOption('dog', 'Cane', 2, true),
            new CatalogFacetOption('cat', 'Gatto', 0),
            new CatalogFacetOption('other', 'Altro', 0),
        ])
            ->and(facetGroup($results, CatalogFacet::Species)->selected)->toBe('dog')
            ->and(facetGroup($results, CatalogFacet::Size)->usesSelect())->toBeFalse()
            ->and(facetGroup($results, CatalogFacet::Region)->options)->toEqual([new CatalogFacetOption('Lazio', 'Lazio', 2)])
            ->and(facetGroup($results, CatalogFacet::Organization)->usesSelect())->toBeTrue()
            ->and(facetGroup($results, CatalogFacet::Organization)->options)->toEqual([new CatalogFacetOption('aurora', 'Rifugio Aurora', 2)]);
    });

    it('keeps the chosen organization and region among the options even with no animals left', function (): void {
        $aurora = Organization::factory()->verified()->create(['name' => 'Rifugio Aurora', 'slug' => 'aurora', 'region' => 'Lazio']);
        Animal::factory()->available()->forOrganization($aurora)->create(['species' => Species::Dog]);

        $results = searchCatalog(new CatalogQuery(species: 'cat', region: 'Lazio', organization: 'aurora'));

        expect(facetGroup($results, CatalogFacet::Organization)->options)->toEqual([new CatalogFacetOption('aurora', 'Rifugio Aurora', 0, true)])
            ->and(facetGroup($results, CatalogFacet::Region)->options)->toEqual([new CatalogFacetOption('Lazio', 'Lazio', 0, true)]);
    });

    it('describes the active filters as removable chips', function (): void {
        Organization::factory()->verified()->create(['name' => 'Rifugio Aurora', 'slug' => 'aurora', 'region' => 'Lazio']);

        $chips = searchCatalog(new CatalogQuery(search: 'rex', species: 'cat', sex: 'female', size: 'large', region: 'Lazio', organization: 'aurora'))->activeFilters;

        expect(array_map(fn (ActiveCatalogFilter $chip): array => [$chip->key, $chip->label, $chip->description], $chips))->toBe([
            ['q', '«rex»', 'Ricerca: «rex»'],
            ['species', 'Gatto', 'Specie: Gatto'],
            ['sex', 'Femmina', 'Sesso: Femmina'],
            ['size', 'Grande', 'Taglia: Grande'],
            ['region', 'Lazio', 'Regione: Lazio'],
            ['organization', 'Rifugio Aurora', 'Ente: Rifugio Aurora'],
        ]);
    });
});
