<?php

declare(strict_types=1);

namespace App\Support\Catalog;

use App\Enums\CatalogFacet;

/**
 * Una faccetta del catalogo con i suoi valori e i conteggi, per il pannello dei filtri.
 *
 * Specie, sesso e taglia hanno pochi valori fissi: tutti mostrati (anche a zero, così il
 * pannello non cambia forma), come gruppo di radio. Regione ed ente hanno valori aperti:
 * solo quelli con animali (più quello scelto), in un select.
 */
final readonly class CatalogFacetGroup
{
    /**
     * @param  list<CatalogFacetOption>  $options
     */
    public function __construct(
        public CatalogFacet $facet,
        public array $options,
        public ?string $selected = null,
    ) {}

    public function usesSelect(): bool
    {
        return in_array($this->facet, [CatalogFacet::Region, CatalogFacet::Organization], true);
    }

    public function isEmpty(): bool
    {
        return $this->options === [];
    }
}
