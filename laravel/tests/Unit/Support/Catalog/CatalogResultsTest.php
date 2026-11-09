<?php

declare(strict_types=1);

use App\Data\Catalog\CatalogQuery;
use App\Enums\CatalogSort;
use App\Enums\Species;
use App\Models\Animal;
use App\Support\Catalog\CatalogFilters;
use App\Support\Catalog\CatalogResults;
use Illuminate\Support\Collection;

/**
 * @param  int  $shown  animali nelle pagine caricate
 */
function catalogResults(int $total, int $firstPage = 1, int $lastLoadedPage = 1, int $shown = 0, ?CatalogFilters $filters = null, CatalogSort $sort = CatalogSort::Relevance): CatalogResults
{
    /** @var Collection<int, Animal> $animals */
    $animals = new Collection(array_fill(0, $shown, new Animal));

    return new CatalogResults($animals, $total, $firstPage, $lastLoadedPage, $filters ?? new CatalogFilters, $sort);
}

it('clamps the requested pages', function (): void {
    $query = new CatalogQuery(page: -3, pages: 99);

    expect($query->firstPage())->toBe(1)
        ->and($query->loadedPages())->toBe(CatalogQuery::MAX_PAGES)
        ->and((new CatalogQuery(pages: 0))->loadedPages())->toBe(1);
});

it('knows the pages before and after the loaded ones', function (): void {
    $results = catalogResults(total: 50, firstPage: 2, lastLoadedPage: 3, shown: 24);

    expect($results->lastPage())->toBe(5)
        ->and($results->hasMore())->toBeTrue()
        ->and($results->nextPage())->toBe(4)
        ->and($results->previousPage())->toBe(1)
        ->and($results->loadedPages())->toBe(2)
        ->and($results->firstItem())->toBe(13)
        ->and($results->lastItem())->toBe(36)
        ->and($results->showsEverything())->toBeFalse();
});

it('has one page and nothing more when everything fits', function (): void {
    $results = catalogResults(total: 7, shown: 7);

    expect($results->lastPage())->toBe(1)
        ->and($results->hasMore())->toBeFalse()
        ->and($results->canLoadMore())->toBeFalse()
        ->and($results->previousPage())->toBeNull()
        ->and($results->showsEverything())->toBeTrue()
        ->and(catalogResults(total: 0)->lastPage())->toBe(1);
});

it('loads automatically only the first pages, then up to the maximum on request', function (): void {
    expect(catalogResults(total: 500, lastLoadedPage: 1, shown: 12)->canAutoLoad())->toBeTrue()
        ->and(catalogResults(total: 500, lastLoadedPage: CatalogResults::AUTO_LOAD_PAGES, shown: 36)->canAutoLoad())->toBeFalse()
        ->and(catalogResults(total: 500, lastLoadedPage: CatalogResults::AUTO_LOAD_PAGES, shown: 36)->canLoadMore())->toBeTrue()
        ->and(catalogResults(total: 500, lastLoadedPage: CatalogQuery::MAX_PAGES, shown: 120)->canLoadMore())->toBeFalse();
});

it('summarises the results for the live region', function (): void {
    expect(catalogResults(total: 0)->summary())->toBe('Nessun animale trovato')
        ->and(catalogResults(total: 1, shown: 1)->summary())->toBe('1 animale trovato')
        ->and(catalogResults(total: 7, shown: 7)->summary())->toBe('7 animali trovati')
        ->and(catalogResults(total: 1250, lastLoadedPage: 2, shown: 24)->summary())->toBe('1.250 animali trovati, mostrati dal 1 al 24');

    app()->setLocale('en');

    expect(catalogResults(total: 1250, firstPage: 3, lastLoadedPage: 3, shown: 12)->summary())->toBe('1,250 animals found, showing 25 to 36');
});

it('builds page links with the active filters and sort, without page 1', function (): void {
    $results = catalogResults(total: 50, filters: new CatalogFilters(search: 'rex', species: Species::Dog), sort: CatalogSort::Name);

    expect($results->stateParameters())->toBe(['q' => 'rex', 'species' => 'dog', 'sort' => 'name'])
        ->and($results->pageUrl(1))->toBe(route('catalog', ['q' => 'rex', 'species' => 'dog', 'sort' => 'name']))
        ->and($results->pageUrl(3))->toBe(route('catalog', ['q' => 'rex', 'species' => 'dog', 'sort' => 'name', 'page' => 3]))
        ->and(catalogResults(total: 50)->pageUrl(2))->toBe(route('catalog', ['page' => 2]));
});
