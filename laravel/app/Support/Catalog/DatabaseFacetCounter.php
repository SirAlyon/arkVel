<?php

declare(strict_types=1);

namespace App\Support\Catalog;

use App\Enums\CatalogFacet;
use App\Models\Animal;
use App\Models\Organization;
use App\Support\Catalog\Contracts\CountsCatalogFacets;
use Illuminate\Database\Eloquent\Builder;

/**
 * Conteggi delle faccette sul database (GROUP BY), per i driver di Scout senza facet
 * distribution: `collection` (test), `database`, `null`.
 *
 * - la ricerca libera passa comunque da Scout (`keys()`), così i conteggi seguono la
 *   stessa corrispondenza testuale dei risultati, qualunque sia il driver;
 * - i filtri sono gli stessi vincoli di {@see CatalogFilters::constraints()} e la
 *   visibilità è lo scope {@see Animal::publiclyVisible()}: niente bozze, adottati o enti
 *   non verificati, nemmeno nei numeri;
 * - la regione è un dato dell'ente: si conta per ente e si somma per regione.
 *
 * Una query per faccetta (cinque in tutto): adatta a test e piccoli cataloghi; in
 * produzione il binding usa {@see MeilisearchFacetCounter}.
 */
final readonly class DatabaseFacetCounter implements CountsCatalogFacets
{
    public function count(CatalogFilters $filters): FacetCounts
    {
        $matchingIds = $this->textMatches($filters->search);
        $counts = new FacetCounts;

        foreach (CatalogFacet::cases() as $facet) {
            $scoped = $filters->without($facet);

            $counts = $counts->with($facet, $scoped->matchesNothing() ? [] : $this->countBy($facet, $scoped, $matchingIds));
        }

        return $counts;
    }

    /**
     * Id degli animali che corrispondono alla ricerca libera, o null senza ricerca.
     *
     * @return list<int>|null
     */
    private function textMatches(string $search): ?array
    {
        if ($search === '') {
            return null;
        }

        return Animal::search($search)->keys()
            ->map(static fn (int|string $id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  list<int>|null  $matchingIds
     * @return array<string, int>
     */
    private function countBy(CatalogFacet $facet, CatalogFilters $filters, ?array $matchingIds): array
    {
        $column = $facet === CatalogFacet::Region ? CatalogFacet::Organization->indexField() : $facet->indexField();

        $counts = $this->visibleAnimals($filters, $matchingIds)
            ->toBase()
            ->whereNotNull($column)
            ->groupBy($column)
            ->select($column.' as value')
            ->selectRaw('count(*) as aggregate')
            ->pluck('aggregate', 'value')
            ->mapWithKeys(static fn (int|string $count, int|string $value): array => [(string) $value => (int) $count])
            ->all();

        return $facet === CatalogFacet::Region ? $this->sumByRegion($counts) : $counts;
    }

    /**
     * @param  list<int>|null  $matchingIds
     * @return Builder<Animal>
     */
    private function visibleAnimals(CatalogFilters $filters, ?array $matchingIds): Builder
    {
        $query = Animal::query()->publiclyVisible();

        if ($matchingIds !== null) {
            $query->whereKey($matchingIds);
        }

        foreach ($filters->constraints() as $constraint) {
            $query->whereIn($constraint->field, $constraint->values);
        }

        return $query;
    }

    /**
     * Da «ente => animali» a «regione => animali» (enti verificati con una regione).
     *
     * @param  array<string, int>  $countsByOrganization
     * @return array<string, int>
     */
    private function sumByRegion(array $countsByOrganization): array
    {
        if ($countsByOrganization === []) {
            return [];
        }

        $regions = Organization::query()
            ->verified()
            ->whereKey(array_keys($countsByOrganization))
            ->whereNotNull('region')
            ->pluck('region', 'id');

        $counts = [];

        foreach ($regions as $id => $region) {
            $region = (string) $region;
            $counts[$region] = ($counts[$region] ?? 0) + ($countsByOrganization[(string) $id] ?? 0);
        }

        return $counts;
    }
}
