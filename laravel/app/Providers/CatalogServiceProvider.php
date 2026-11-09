<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Catalog\Contracts\CountsCatalogFacets;
use App\Support\Catalog\DatabaseFacetCounter;
use App\Support\Catalog\MeilisearchFacetCounter;
use App\Support\Catalog\VerifiedOrganizationIds;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Catalogo pubblico (UI-6b): sceglie come contare le faccette (Dependency Inversion).
 *
 * Con il driver Scout `meilisearch` i conteggi arrivano dalla facet distribution
 * ({@see MeilisearchFacetCounter}); con gli altri driver (`collection` nei test,
 * `database`, `null`) da GROUP BY sul database ({@see DatabaseFacetCounter}). La scelta
 * avviene alla risoluzione, quindi segue la config effettiva (anche cambiata in un test).
 *
 * {@see VerifiedOrganizationIds} è `scoped`: una sola query per richiesta (anche per ogni
 * richiesta Livewire), condivisa da tutti i conteggi della pagina.
 */
class CatalogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(VerifiedOrganizationIds::class);

        $this->app->bind(CountsCatalogFacets::class, static fn (Application $app): CountsCatalogFacets => $app['config']->get('scout.driver') === 'meilisearch'
            ? $app->make(MeilisearchFacetCounter::class)
            : $app->make(DatabaseFacetCounter::class));
    }
}
