<?php

declare(strict_types=1);

namespace App\Support\Catalog;

use App\Enums\CatalogFacet;
use App\Models\Animal;
use App\Support\Catalog\Contracts\CountsCatalogFacets;
use Meilisearch\Client;
use Meilisearch\Contracts\SearchQuery;

/**
 * Conteggi delle faccette dalla facet distribution di Meilisearch, in UNA richiesta
 * multi-search (`limit: 0`, solo i numeri):
 *
 * - una query con tutti i filtri per le faccette non attive;
 * - una query per ogni faccetta attiva, con gli altri filtri e senza la sua scelta
 *   (conteggi disgiuntivi: gli altri valori della faccetta restano scopribili).
 *
 * Visibilità: l'indice contiene solo animali pubblicamente visibili
 * (Animal::shouldBeSearchable()), ma può restare indietro rispetto allo stato dell'ente
 * (un ente sospeso dopo l'indicizzazione: App\Observers\OrganizationObserver lo riallinea,
 * ma un indice importato prima o una coda in ritardo lascerebbero animali non visibili).
 * Ogni query porta quindi anche il vincolo `organization_id IN [enti verificati]`
 * ({@see VerifiedOrganizationIds}, una query per richiesta), come la callback Eloquent dei
 * risultati in {@see CatalogScoutQuery}: i numeri non contano mai animali non mostrati.
 *
 * Gli attributi contati devono essere tra i `filterableAttributes` dell'indice
 * (config/scout.php); Meilisearch restituisce al massimo `maxValuesPerFacet` valori per
 * faccetta (1000 per l'indice degli animali, config/scout.php → `faceting`).
 */
final readonly class MeilisearchFacetCounter implements CountsCatalogFacets
{
    public function __construct(
        private Client $client,
        private VerifiedOrganizationIds $verifiedOrganizations,
    ) {}

    public function count(CatalogFilters $filters): FacetCounts
    {
        /** @var list<array{0: CatalogFilters, 1: list<CatalogFacet>}> $plan */
        $plan = [];
        $inactive = array_values(array_filter(CatalogFacet::cases(), static fn (CatalogFacet $facet): bool => ! $filters->has($facet)));

        if ($inactive !== []) {
            $plan[] = [$filters, $inactive];
        }

        foreach ($filters->activeFacets() as $facet) {
            $plan[] = [$filters->without($facet), [$facet]];
        }

        $counts = new FacetCounts;
        $queries = [];
        $planned = [];
        $visibility = new CatalogConstraint(CatalogFacet::Organization->indexField(), $this->verifiedOrganizations->all());

        foreach ($plan as [$scoped, $facets]) {
            if ($scoped->matchesNothing() || $visibility->matchesNothing()) {
                foreach ($facets as $facet) {
                    $counts = $counts->with($facet, []);
                }

                continue;
            }

            $queries[] = $this->query($scoped, $facets, $visibility);
            $planned[] = $facets;
        }

        if ($queries === []) {
            return $counts;
        }

        /** @var array{results?: list<array{facetDistribution?: array<string, array<string, int>>}>} $response */
        $response = $this->client->multiSearch($queries);

        foreach ($planned as $index => $facets) {
            $distribution = $response['results'][$index]['facetDistribution'] ?? [];

            foreach ($facets as $facet) {
                $counts = $counts->with($facet, $distribution[$facet->indexField()] ?? []);
            }
        }

        return $counts;
    }

    /**
     * I vincoli dei filtri, più quello di visibilità (sempre ultimo).
     *
     * @param  list<CatalogFacet>  $facets
     */
    private function query(CatalogFilters $filters, array $facets, CatalogConstraint $visibility): SearchQuery
    {
        return (new SearchQuery)
            ->setIndexUid((new Animal)->searchableAs())
            ->setQuery($filters->search)
            ->setFacets(array_map(static fn (CatalogFacet $facet): string => $facet->indexField(), $facets))
            ->setFilter(array_map(self::filter(...), [...$filters->constraints(), $visibility]))
            ->setLimit(0);
    }

    /**
     * `campo IN [valori]`, con le stringhe tra virgolette ed escape (stesso formato del
     * motore Meilisearch di Scout).
     *
     * @return non-empty-string
     */
    private static function filter(CatalogConstraint $constraint): string
    {
        $values = array_map(
            static fn (int|string $value): string => is_int($value) ? (string) $value : '"'.addcslashes($value, '"\\').'"',
            $constraint->values,
        );

        return $constraint->field.' IN ['.implode(', ', $values).']';
    }
}
