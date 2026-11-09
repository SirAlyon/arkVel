<?php

declare(strict_types=1);

namespace App\Support\Catalog;

use App\Enums\CatalogFacet;
use App\Enums\Sex;
use App\Enums\Size;
use App\Enums\Species;
use App\Models\Organization;

/**
 * Filtri del catalogo già risolti e validi (vedi ResolveCatalogFilters): enum veri, un
 * ente verificato, una regione con gli id dei suoi enti verificati. È l'unica fonte dei
 * vincoli di ricerca, dei chip attivi e dei parametri degli URL.
 *
 * La regione è un dato dell'ente, non dell'animale: diventa un vincolo su
 * `organization_id` (gli enti verificati della regione), che vale allo stesso modo su
 * Meilisearch, sul driver collection/database di Scout e sulle query di conteggio.
 */
final readonly class CatalogFilters
{
    /**
     * @param  list<int>  $regionOrganizationIds  enti verificati della regione
     */
    public function __construct(
        public string $search = '',
        public ?Species $species = null,
        public ?Sex $sex = null,
        public ?Size $size = null,
        public ?string $region = null,
        public array $regionOrganizationIds = [],
        public ?Organization $organization = null,
    ) {}

    public function has(CatalogFacet $facet): bool
    {
        return $this->value($facet) !== null;
    }

    /**
     * Valore del filtro come compare nella query string (enum, regione, slug dell'ente).
     */
    public function value(CatalogFacet $facet): ?string
    {
        return match ($facet) {
            CatalogFacet::Species => $this->species?->value,
            CatalogFacet::Sex => $this->sex?->value,
            CatalogFacet::Size => $this->size?->value,
            CatalogFacet::Region => $this->region,
            CatalogFacet::Organization => $this->organization?->slug,
        };
    }

    /**
     * Gli stessi filtri senza una faccetta: base dei conteggi "disgiuntivi" (i valori di
     * una faccetta si contano ignorando la sua scelta, così restano tutti scopribili).
     */
    public function without(CatalogFacet $facet): self
    {
        return new self(
            search: $this->search,
            species: $facet === CatalogFacet::Species ? null : $this->species,
            sex: $facet === CatalogFacet::Sex ? null : $this->sex,
            size: $facet === CatalogFacet::Size ? null : $this->size,
            region: $facet === CatalogFacet::Region ? null : $this->region,
            regionOrganizationIds: $facet === CatalogFacet::Region ? [] : $this->regionOrganizationIds,
            organization: $facet === CatalogFacet::Organization ? null : $this->organization,
        );
    }

    /**
     * Faccette attive, nell'ordine dei casi dell'enum.
     *
     * @return list<CatalogFacet>
     */
    public function activeFacets(): array
    {
        return array_values(array_filter(CatalogFacet::cases(), $this->has(...)));
    }

    public function hasFacetFilters(): bool
    {
        return $this->activeFacets() !== [];
    }

    public function isFiltered(): bool
    {
        return $this->search !== '' || $this->hasFacetFilters();
    }

    /**
     * Vincoli `campo IN valori` per il motore e per il database. Ente e regione insistono
     * entrambi su `organization_id`: diventano un solo vincolo con l'intersezione (un ente
     * fuori dalla regione scelta non dà risultati).
     *
     * @return list<CatalogConstraint>
     */
    public function constraints(): array
    {
        $constraints = [];

        foreach ([$this->species, $this->sex, $this->size] as $enum) {
            if ($enum !== null) {
                $constraints[] = new CatalogConstraint(self::fieldOf($enum), [$enum->value]);
            }
        }

        $organizationIds = null;

        if ($this->region !== null) {
            $organizationIds = $this->regionOrganizationIds;
        }

        if ($this->organization !== null) {
            $id = (int) $this->organization->getKey();
            $organizationIds = $organizationIds === null ? [$id] : array_values(array_intersect($organizationIds, [$id]));
        }

        if ($organizationIds !== null) {
            $constraints[] = new CatalogConstraint(CatalogFacet::Organization->indexField(), $organizationIds);
        }

        return $constraints;
    }

    /**
     * Combinazione impossibile (es. un ente fuori dalla regione): nessun animale.
     */
    public function matchesNothing(): bool
    {
        foreach ($this->constraints() as $constraint) {
            if ($constraint->matchesNothing()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parametri della query string per questi filtri (solo quelli attivi).
     *
     * @return array<string, string>
     */
    public function queryParameters(): array
    {
        $parameters = $this->search !== '' ? ['q' => $this->search] : [];

        foreach ($this->activeFacets() as $facet) {
            $parameters[$facet->value] = (string) $this->value($facet);
        }

        return $parameters;
    }

    private static function fieldOf(Species|Sex|Size $enum): string
    {
        return match (true) {
            $enum instanceof Species => CatalogFacet::Species->indexField(),
            $enum instanceof Sex => CatalogFacet::Sex->indexField(),
            $enum instanceof Size => CatalogFacet::Size->indexField(),
        };
    }
}
