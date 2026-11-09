<?php

declare(strict_types=1);

namespace App\Support\Catalog;

use App\Data\Catalog\CatalogQuery;
use App\Enums\Sex;
use App\Enums\Size;
use App\Enums\Species;
use App\Models\Organization;

/**
 * Dai valori grezzi della query string ai filtri validi ({@see CatalogFilters}).
 *
 * Un valore sconosciuto viene scartato (il filtro non si applica e non compare tra i
 * chip), non trasformato in una ricerca vuota: un link vecchio o scritto a mano mostra
 * comunque il catalogo. L'ente e la regione si risolvono SOLO tra gli enti verificati:
 * lo slug di un ente in attesa o sospeso non rivela nulla (né nome né animali).
 */
final readonly class ResolveCatalogFilters
{
    /** Lunghezza massima della ricerca libera (oltre è rumore o un abuso). */
    public const int MAX_SEARCH_LENGTH = 100;

    public function __invoke(CatalogQuery $query): CatalogFilters
    {
        $region = trim($query->region);
        $regionOrganizationIds = $region === '' ? [] : $this->regionOrganizationIds($region);

        return new CatalogFilters(
            search: mb_substr(trim($query->search), 0, self::MAX_SEARCH_LENGTH),
            species: Species::tryFrom($query->species),
            sex: Sex::tryFrom($query->sex),
            size: Size::tryFrom($query->size),
            region: $regionOrganizationIds === [] ? null : $region,
            regionOrganizationIds: $regionOrganizationIds,
            organization: $this->organization(trim($query->organization)),
        );
    }

    private function organization(string $slug): ?Organization
    {
        if ($slug === '') {
            return null;
        }

        return Organization::query()->verified()->where('slug', $slug)->first();
    }

    /**
     * @return list<int>
     */
    private function regionOrganizationIds(string $region): array
    {
        return Organization::query()
            ->verified()
            ->where('region', $region)
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (int|string $id): int => (int) $id)
            ->values()
            ->all();
    }
}
