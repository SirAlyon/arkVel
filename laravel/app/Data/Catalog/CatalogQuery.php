<?php

declare(strict_types=1);

namespace App\Data\Catalog;

use App\Actions\Catalog\SearchCatalog;
use Spatie\LaravelData\Data;

/**
 * Richiesta del catalogo pubblico così come arriva dalla pagina (query string e stato
 * del componente Livewire), per {@see SearchCatalog}.
 *
 * I valori sono quelli grezzi dell'URL: specie, sesso o taglia sconosciuti, un ente non
 * verificato o una regione senza enti vengono scartati da ResolveCatalogFilters (il
 * filtro non si applica e non compare tra i chip), mai passati al motore di ricerca.
 *
 * - `page`: la prima pagina mostrata (`?page=N`, link di paginazione e SEO), almeno 1;
 * - `pages`: quante pagine sono caricate a partire da `page` («Carica altri» e
 *   caricamento continuo), tra 1 e {@see self::MAX_PAGES}.
 */
final class CatalogQuery extends Data
{
    /** Animali per pagina. */
    public const int PER_PAGE = 12;

    /**
     * Pagine caricabili di seguito nella stessa vista (120 animali): oltre, si prosegue
     * con i link di paginazione, così il DOM e la richiesta restano contenuti.
     */
    public const int MAX_PAGES = 10;

    public function __construct(
        public readonly string $search = '',
        public readonly string $species = '',
        public readonly string $sex = '',
        public readonly string $size = '',
        public readonly string $region = '',
        public readonly string $organization = '',
        public readonly string $sort = '',
        public readonly int $page = 1,
        public readonly int $pages = 1,
    ) {}

    public function firstPage(): int
    {
        return max(1, $this->page);
    }

    public function loadedPages(): int
    {
        return min(self::MAX_PAGES, max(1, $this->pages));
    }
}
