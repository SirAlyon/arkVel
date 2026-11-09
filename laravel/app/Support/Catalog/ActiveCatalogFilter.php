<?php

declare(strict_types=1);

namespace App\Support\Catalog;

use App\Enums\CatalogFacet;

/**
 * Un filtro attivo del catalogo, mostrato come chip rimovibile.
 *
 * - `key`: `q` per la ricerca libera o il valore di una {@see CatalogFacet}, cioè
 *   l'argomento di `removeFilter()` nel componente;
 * - `label`: il testo del chip («Cani», «“rex”»);
 * - `description`: il chip con il suo contesto («Specie: Cani»), per il nome del bottone
 *   di rimozione («Rimuovi Specie: Cani»).
 */
final readonly class ActiveCatalogFilter
{
    public const string SEARCH = 'q';

    public function __construct(
        public string $key,
        public string $label,
        public string $description,
    ) {}

    /**
     * Chip dei filtri attivi, nell'ordine del pannello (prima la ricerca).
     *
     * @return list<self>
     */
    public static function listFrom(CatalogFilters $filters): array
    {
        $chips = [];

        if ($filters->search !== '') {
            $label = __('page_catalog.chips.search', ['search' => $filters->search]);
            $chips[] = new self(self::SEARCH, $label, __('page_catalog.chips.described', ['facet' => __('page_catalog.chips.search_facet'), 'value' => $label]));
        }

        foreach ($filters->activeFacets() as $facet) {
            $label = match ($facet) {
                CatalogFacet::Species => (string) $filters->species?->getLabel(),
                CatalogFacet::Sex => (string) $filters->sex?->getLabel(),
                CatalogFacet::Size => (string) $filters->size?->getLabel(),
                CatalogFacet::Region => (string) $filters->region,
                CatalogFacet::Organization => (string) $filters->organization?->name,
            };

            $chips[] = new self($facet->value, $label, __('page_catalog.chips.described', ['facet' => $facet->label(), 'value' => $label]));
        }

        return $chips;
    }
}
