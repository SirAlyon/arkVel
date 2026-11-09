<?php

declare(strict_types=1);

namespace App\Support\Map;

use App\Actions\Map\BuildCatalogMap;

/**
 * Gli enti della vista «Mappa» del catalogo ({@see BuildCatalogMap}): uno per ente con
 * animali che corrispondono ai filtri correnti, in ordine di nome.
 */
final readonly class CatalogMap
{
    /**
     * @param  list<MapMarker>  $markers
     */
    public function __construct(
        public array $markers = [],
    ) {}

    public function isEmpty(): bool
    {
        return $this->markers === [];
    }

    /**
     * Solo gli enti con una posizione: quelli che la mappa può mostrare.
     *
     * @return list<MapMarker>
     */
    public function located(): array
    {
        return array_values(array_filter($this->markers, static fn (MapMarker $marker): bool => $marker->isLocated()));
    }

    /** Alcuni enti non hanno una posizione: sono solo nell'elenco. */
    public function hasUnlocated(): bool
    {
        return count($this->located()) < count($this->markers);
    }
}
