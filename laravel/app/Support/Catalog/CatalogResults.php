<?php

declare(strict_types=1);

namespace App\Support\Catalog;

use App\Data\Catalog\CatalogQuery;
use App\Enums\CatalogSort;
use App\Models\Animal;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;

/**
 * Esito di una ricerca del catalogo: gli animali delle pagine caricate (da `firstPage` a
 * `lastLoadedPage`), il totale, le faccette con i conteggi e i chip dei filtri attivi.
 *
 * Calcola anche la paginazione per la vista: «Carica altri» (stessa vista, pagine in
 * più), link precedente/successiva (URL `?page=N` per chi non ha JavaScript e per i
 * motori di ricerca, coerenti con App\Support\Seo\PublicPageSeo::catalog()).
 */
final readonly class CatalogResults
{
    /**
     * Pagine caricate da sole scorrendo (wire:intersect): poi si prosegue con il bottone
     * «Carica altri», così il footer della pagina resta raggiungibile.
     */
    public const int AUTO_LOAD_PAGES = 3;

    /**
     * @param  Collection<int, Animal>  $animals
     * @param  list<CatalogFacetGroup>  $facets
     * @param  list<ActiveCatalogFilter>  $activeFilters
     */
    public function __construct(
        public Collection $animals,
        public int $total,
        public int $firstPage,
        public int $lastLoadedPage,
        public CatalogFilters $filters,
        public CatalogSort $sort = CatalogSort::Relevance,
        public array $facets = [],
        public array $activeFilters = [],
    ) {}

    /** Ultima pagina con risultati (1 se non ce ne sono). */
    public function lastPage(): int
    {
        return max(1, (int) ceil($this->total / CatalogQuery::PER_PAGE));
    }

    public function isEmpty(): bool
    {
        return $this->animals->isEmpty();
    }

    /** Ci sono pagine dopo quelle caricate. */
    public function hasMore(): bool
    {
        return $this->lastLoadedPage < $this->lastPage();
    }

    public function nextPage(): int
    {
        return $this->lastLoadedPage + 1;
    }

    public function previousPage(): ?int
    {
        return $this->firstPage > 1 ? $this->firstPage - 1 : null;
    }

    public function loadedPages(): int
    {
        return $this->lastLoadedPage - $this->firstPage + 1;
    }

    /** «Carica altri» resta nella stessa vista fino a CatalogQuery::MAX_PAGES pagine. */
    public function canLoadMore(): bool
    {
        return $this->hasMore() && $this->loadedPages() < CatalogQuery::MAX_PAGES;
    }

    /** Caricamento continuo allo scorrimento, entro AUTO_LOAD_PAGES pagine. */
    public function canAutoLoad(): bool
    {
        return $this->canLoadMore() && $this->loadedPages() < self::AUTO_LOAD_PAGES;
    }

    /** Posizione (da 1) del primo animale mostrato. */
    public function firstItem(): int
    {
        return ($this->firstPage - 1) * CatalogQuery::PER_PAGE + 1;
    }

    /** Posizione dell'ultimo animale mostrato. */
    public function lastItem(): int
    {
        return $this->firstItem() + $this->animals->count() - 1;
    }

    /** Tutti i risultati sono a schermo (nessuna pagina prima né dopo). */
    public function showsEverything(): bool
    {
        return $this->firstPage === 1 && ! $this->hasMore();
    }

    /**
     * Esito per la regione live: «24 animali trovati», con l'intervallo mostrato quando
     * non sono tutti a schermo («…, mostrati dal 1 al 12»).
     */
    public function summary(): string
    {
        $locale = app()->getLocale();
        $summary = trans_choice('page_catalog.results.count', $this->total, ['count' => Number::format($this->total, locale: $locale)]);

        if ($this->isEmpty() || $this->showsEverything()) {
            return $summary;
        }

        return __('page_catalog.results.range', [
            'summary' => $summary,
            'from' => Number::format($this->firstItem(), locale: $locale),
            'to' => Number::format($this->lastItem(), locale: $locale),
        ]);
    }

    /**
     * Parametri della query string per filtri e ordinamento correnti (solo quelli attivi,
     * senza la pagina): link di paginazione e campi nascosti dei moduli senza JavaScript.
     *
     * @return array<string, string>
     */
    public function stateParameters(): array
    {
        return [
            ...$this->filters->queryParameters(),
            ...($this->sort === CatalogSort::Relevance ? [] : ['sort' => $this->sort->value]),
        ];
    }

    /**
     * URL di una pagina del catalogo con i filtri e l'ordinamento correnti (niente
     * `page=1`).
     */
    public function pageUrl(int $page): string
    {
        return route('catalog', [
            ...$this->stateParameters(),
            ...($page > 1 ? ['page' => $page] : []),
        ]);
    }
}
