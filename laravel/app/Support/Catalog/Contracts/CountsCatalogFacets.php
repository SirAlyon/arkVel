<?php

declare(strict_types=1);

namespace App\Support\Catalog\Contracts;

use App\Support\Catalog\CatalogFilters;
use App\Support\Catalog\DatabaseFacetCounter;
use App\Support\Catalog\FacetCounts;
use App\Support\Catalog\MeilisearchFacetCounter;

/**
 * Conta gli animali visibili per ogni valore delle faccette del catalogo, con la ricerca
 * e gli altri filtri attivi (conteggi disgiuntivi: una faccetta ignora la propria scelta).
 *
 * Due implementazioni, scelte in App\Providers\CatalogServiceProvider secondo il driver di
 * Scout: {@see MeilisearchFacetCounter} (facet distribution, una sola
 * richiesta multi-search) e {@see DatabaseFacetCounter} (GROUP BY sul
 * database, per i driver collection/database/null dei test e degli ambienti senza
 * Meilisearch). Entrambe contano solo animali pubblicamente visibili.
 */
interface CountsCatalogFacets
{
    public function count(CatalogFilters $filters): FacetCounts;
}
