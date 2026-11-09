<?php

declare(strict_types=1);

namespace App\Support\Catalog;

use App\Enums\CatalogFacet;

/**
 * Conteggi delle faccette del catalogo: per ogni faccetta, valore => animali.
 *
 * I valori sono quelli dell'indice: l'enum per specie/sesso/taglia, il nome della regione,
 * l'id dell'ente (come stringa, come lo restituisce Meilisearch). I conteggi sono
 * "disgiuntivi": quelli di una faccetta ignorano la sua scelta (vedi
 * {@see CatalogFilters::without()}).
 */
final readonly class FacetCounts
{
    /**
     * @param  array<string, array<string, int>>  $counts  valore della faccetta => (valore => conteggio)
     */
    public function __construct(
        private array $counts = [],
    ) {}

    /**
     * @param  array<int|string, int>  $values
     */
    public function with(CatalogFacet $facet, array $values): self
    {
        $normalized = [];

        foreach ($values as $value => $count) {
            if ((string) $value !== '' && $count > 0) {
                $normalized[(string) $value] = $count;
            }
        }

        return new self([...$this->counts, $facet->value => $normalized]);
    }

    /**
     * Valori con almeno un animale (stringa => conteggio).
     *
     * @return array<string, int>
     */
    public function for(CatalogFacet $facet): array
    {
        return $this->counts[$facet->value] ?? [];
    }

    public function count(CatalogFacet $facet, string $value): int
    {
        return $this->for($facet)[$value] ?? 0;
    }
}
