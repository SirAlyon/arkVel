<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Data\Organizations\OrganizationDirectoryFacets;
use App\Enums\OrganizationType;
use App\Models\Organization;

/**
 * Opzioni dei filtri dell'indice degli enti: le regioni e i tipi presenti tra gli enti
 * verificati (gli enti in attesa o sospesi non devono trapelare neanche da qui).
 * Una sola query sulle coppie distinte (regione, tipo).
 */
final class ListOrganizationDirectoryFacets
{
    public function handle(): OrganizationDirectoryFacets
    {
        $pairs = Organization::query()
            ->verified()
            ->select(['region', 'type'])
            ->distinct()
            ->get();

        $regions = $pairs
            ->pluck('region')
            ->filter(fn (mixed $region): bool => is_string($region) && trim($region) !== '')
            ->unique()
            ->sort(SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();

        $present = $pairs->pluck('type')->all();

        $types = array_values(array_filter(
            OrganizationType::cases(),
            fn (OrganizationType $type): bool => in_array($type, $present, true),
        ));

        /** @var list<string> $regions */
        return new OrganizationDirectoryFacets(regions: $regions, types: $types);
    }
}
