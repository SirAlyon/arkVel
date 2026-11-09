<?php

declare(strict_types=1);

namespace App\Support\Catalog;

use App\Enums\CatalogFacet;
use App\Enums\Sex;
use App\Enums\Size;
use App\Enums\Species;
use App\Models\Organization;
use BackedEnum;
use Filament\Support\Contracts\HasLabel;

/**
 * Dai conteggi grezzi ({@see FacetCounts}) ai gruppi del pannello dei filtri, con le
 * etichette nella lingua corrente.
 *
 * - specie, sesso, taglia: tutti i casi dell'enum, anche a zero;
 * - ente: solo enti VERIFICATI con animali (più quello scelto), per nome; il valore è lo
 *   slug (URL leggibili), il conteggio arriva per id;
 * - regione: le regioni con animali (più quella scelta), in ordine alfabetico.
 */
final readonly class BuildCatalogFacetGroups
{
    /**
     * @return list<CatalogFacetGroup>
     */
    public function __invoke(FacetCounts $counts, CatalogFilters $filters): array
    {
        return [
            $this->enumGroup(CatalogFacet::Species, Species::cases(), $counts, $filters),
            $this->enumGroup(CatalogFacet::Sex, Sex::cases(), $counts, $filters),
            $this->enumGroup(CatalogFacet::Size, Size::cases(), $counts, $filters),
            $this->regionGroup($counts, $filters),
            $this->organizationGroup($counts, $filters),
        ];
    }

    /**
     * @param  list<BackedEnum&HasLabel>  $cases
     */
    private function enumGroup(CatalogFacet $facet, array $cases, FacetCounts $counts, CatalogFilters $filters): CatalogFacetGroup
    {
        $selected = $filters->value($facet);

        return new CatalogFacetGroup($facet, array_map(
            static fn (BackedEnum&HasLabel $case): CatalogFacetOption => new CatalogFacetOption(
                value: (string) $case->value,
                label: (string) $case->getLabel(),
                count: $counts->count($facet, (string) $case->value),
                selected: $selected === (string) $case->value,
            ),
            $cases,
        ), $selected);
    }

    private function regionGroup(FacetCounts $counts, CatalogFilters $filters): CatalogFacetGroup
    {
        $values = $counts->for(CatalogFacet::Region);

        if ($filters->region !== null) {
            $values[$filters->region] ??= 0;
        }

        // Difesa in profondità: l'indice può restare indietro (un ente sospeso dopo
        // l'indicizzazione), quindi solo le regioni di enti verificati.
        $regions = $values === [] ? [] : Organization::query()
            ->verified()
            ->whereIn('region', array_map(strval(...), array_keys($values)))
            ->distinct()
            ->pluck('region')
            ->map(strval(...))
            ->all();
        natcasesort($regions);

        return new CatalogFacetGroup(CatalogFacet::Region, array_values(array_map(
            static fn (string $region): CatalogFacetOption => new CatalogFacetOption(
                value: $region,
                label: $region,
                count: $values[$region] ?? 0,
                selected: $region === $filters->region,
            ),
            $regions,
        )), $filters->region);
    }

    private function organizationGroup(FacetCounts $counts, CatalogFilters $filters): CatalogFacetGroup
    {
        $values = $counts->for(CatalogFacet::Organization);
        $ids = array_map(intval(...), array_keys($values));

        if ($filters->organization !== null) {
            $ids[] = (int) $filters->organization->getKey();
        }

        $organizations = $ids === [] ? collect() : Organization::query()
            ->verified()
            ->whereKey(array_unique($ids))
            ->orderBy('name')
            ->get(['id', 'slug', 'name']);

        $selected = $filters->organization?->slug;

        return new CatalogFacetGroup(CatalogFacet::Organization, $organizations->map(
            static fn (Organization $organization): CatalogFacetOption => new CatalogFacetOption(
                value: $organization->slug,
                label: $organization->name,
                count: $values[(string) $organization->getKey()] ?? 0,
                selected: $organization->slug === $selected,
            ),
        )->values()->all(), $selected);
    }
}
