<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Data\Catalog\CatalogQuery;
use App\Enums\CatalogSort;
use App\Models\Animal;
use App\Support\Catalog\ActiveCatalogFilter;
use App\Support\Catalog\BuildCatalogFacetGroups;
use App\Support\Catalog\CatalogFilters;
use App\Support\Catalog\CatalogResults;
use App\Support\Catalog\CatalogScoutQuery;
use App\Support\Catalog\Contracts\CountsCatalogFacets;
use App\Support\Catalog\ResolveCatalogFilters;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Ricerca del catalogo pubblico (UI-6b): filtri validi, animali delle pagine caricate,
 * totale, faccette con i conteggi, chip dei filtri attivi.
 *
 * - visibilità: solo animali pubblicamente visibili (CatalogScoutQuery e i contatori);
 * - pagine: da `page` per `pages` pagine (caricamento continuo). Se l'intervallo è
 *   allineato (sempre quando si parte da pagina 1) basta una sola richiesta al motore
 *   con una pagina più grande; altrimenti una richiesta per pagina (al massimo
 *   CatalogQuery::MAX_PAGES);
 * - una combinazione impossibile (ente fuori dalla regione) non interroga il motore.
 */
final readonly class SearchCatalog
{
    public function __construct(
        private ResolveCatalogFilters $resolveFilters,
        private CatalogScoutQuery $scoutQuery,
        private CountsCatalogFacets $facetCounter,
        private BuildCatalogFacetGroups $facetGroups,
    ) {}

    public function __invoke(CatalogQuery $query): CatalogResults
    {
        $filters = ($this->resolveFilters)($query);
        $sort = CatalogSort::fromQuery($query->sort);
        $firstPage = $query->firstPage();

        [$animals, $total] = $filters->matchesNothing()
            ? [new Collection, 0]
            : $this->fetch($filters, $sort, $firstPage, $query->loadedPages());

        $lastPage = max(1, (int) ceil($total / CatalogQuery::PER_PAGE));

        return new CatalogResults(
            animals: $animals,
            total: $total,
            firstPage: $firstPage,
            lastLoadedPage: max($firstPage, min($firstPage + $query->loadedPages() - 1, $lastPage)),
            filters: $filters,
            sort: $sort,
            facets: ($this->facetGroups)($this->facetCounter->count($filters), $filters),
            activeFilters: ActiveCatalogFilter::listFrom($filters),
        );
    }

    /**
     * @return array{0: Collection<int, Animal>, 1: int}
     */
    private function fetch(CatalogFilters $filters, CatalogSort $sort, int $firstPage, int $pages): array
    {
        // Intervallo allineato a pagine grandi `pages` volte: una sola richiesta.
        if (($firstPage - 1) % $pages === 0) {
            $paginator = $this->page($filters, $sort, CatalogQuery::PER_PAGE * $pages, intdiv($firstPage - 1, $pages) + 1);

            return [$paginator->getCollection(), $paginator->total()];
        }

        $first = $this->page($filters, $sort, CatalogQuery::PER_PAGE, $firstPage);
        $animals = $first->getCollection();
        $lastPage = $first->lastPage();

        for ($page = $firstPage + 1; $page < $firstPage + $pages && $page <= $lastPage; $page++) {
            $animals = $animals->merge($this->page($filters, $sort, CatalogQuery::PER_PAGE, $page)->getCollection());
        }

        return [$animals->values(), $first->total()];
    }

    /**
     * @return LengthAwarePaginator<int, Animal>
     */
    private function page(CatalogFilters $filters, CatalogSort $sort, int $perPage, int $page): LengthAwarePaginator
    {
        return $this->scoutQuery->paginate($filters, $sort, $perPage, $page);
    }
}
