<?php

declare(strict_types=1);

namespace App\Actions\Map;

use App\Actions\Catalog\SearchCatalog;
use App\Enums\CatalogFacet;
use App\Models\Organization;
use App\Support\Catalog\CatalogFacetGroup;
use App\Support\Catalog\CatalogResults;
use App\Support\Map\ApproximateLocation;
use App\Support\Map\CatalogMap;
use App\Support\Map\MapMarker;
use App\Support\Site\PublicLocation;
use Illuminate\Support\Number;

/**
 * Vista «Mappa» del catalogo (UI-7): gli enti con animali che corrispondono ai filtri
 * correnti, uno per ente, con il numero di animali, la località pubblica, la posizione
 * APPROSSIMATA ({@see ApproximateLocation}) e il link al catalogo filtrato per ente.
 *
 * Nessuna ricerca in più: i numeri per ente sono quelli della faccetta «Ente» che
 * {@see SearchCatalog} ha già contato (stessa visibilità: solo animali
 * publiclyVisible di enti verificati). Quei conteggi sono disgiuntivi (ignorano la scelta
 * dell'ente): con un ente scelto resta solo il suo, che è esattamente il numero dei
 * risultati. Poi una sola query sugli enti VERIFICATI per nome, località e coordinate.
 * Meilisearch restituisce al massimo `maxValuesPerFacet` enti per faccetta: l'indice degli
 * animali lo alza a 1000 (config/scout.php, `faceting`), così la mappa non perde enti.
 */
final readonly class BuildCatalogMap
{
    public function __invoke(CatalogResults $results): CatalogMap
    {
        $counts = $this->countsByOrganization($results);

        if ($counts === []) {
            return new CatalogMap;
        }

        $organizations = Organization::query()
            ->verified()
            ->whereIn('slug', array_keys($counts))
            ->orderBy('name')
            ->get(['id', 'slug', 'name', 'city', 'region', 'latitude', 'longitude']);

        $locale = app()->getLocale();

        $markers = [];

        foreach ($organizations as $organization) {
            $count = $counts[$organization->slug];

            $markers[] = new MapMarker(
                id: $organization->slug,
                title: $organization->name,
                description: PublicLocation::of($organization),
                count: $count,
                countLabel: trans_choice('page_catalog.map.animals', $count, ['count' => Number::format($count, locale: $locale)]),
                url: route('catalog', [...$results->stateParameters(), CatalogFacet::Organization->value => $organization->slug]),
                location: ApproximateLocation::of($organization),
            );
        }

        return new CatalogMap($markers);
    }

    /**
     * Slug dell'ente => animali che corrispondono ai filtri.
     *
     * @return array<string, int>
     */
    private function countsByOrganization(CatalogResults $results): array
    {
        if ($results->total === 0) {
            return [];
        }

        $group = array_find($results->facets, static fn (CatalogFacetGroup $group): bool => $group->facet === CatalogFacet::Organization);
        $selected = $results->filters->organization?->slug;
        $counts = [];

        foreach ($group->options ?? [] as $option) {
            if ($option->count > 0 && ($selected === null || $option->value === $selected)) {
                $counts[$option->value] = $option->count;
            }
        }

        return $counts;
    }
}
