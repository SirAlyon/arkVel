<?php

declare(strict_types=1);

namespace App\Data\Organizations;

use App\Actions\Organizations\ListOrganizationDirectoryFacets;
use App\Actions\Organizations\ListPublicOrganizations;
use App\Enums\OrganizationType;
use Spatie\LaravelData\Data;

/**
 * Filtri dell'indice pubblico degli enti ({@see ListPublicOrganizations}), già
 * normalizzati: ricerca per nome senza spazi ai lati e al massimo
 * {@see self::MAX_SEARCH_LENGTH} caratteri, una regione tra quelle degli enti verificati
 * ({@see ListOrganizationDirectoryFacets}), tipo come enum. Un valore vuoto o non
 * riconosciuto (un tipo o una regione inventati nella query string) vuol dire «nessun
 * filtro», mai un errore né una pagina vuota per costruzione.
 */
final class OrganizationDirectoryFilters extends Data
{
    public const int MAX_SEARCH_LENGTH = 100;

    public function __construct(
        public readonly ?string $search = null,
        public readonly ?string $region = null,
        public readonly ?OrganizationType $type = null,
    ) {}

    /**
     * Dai valori grezzi del componente (query string comprese).
     *
     * @param  list<string>  $knownRegions  le regioni degli enti verificati: le uniche accettate
     */
    public static function fromInput(string $search, string $region, string $type, array $knownRegions): self
    {
        $search = mb_substr(trim($search), 0, self::MAX_SEARCH_LENGTH);
        $region = trim($region);

        return new self(
            search: $search !== '' ? $search : null,
            region: in_array($region, $knownRegions, true) ? $region : null,
            type: OrganizationType::tryFrom($type),
        );
    }

    public function isFiltered(): bool
    {
        return $this->search !== null || $this->region !== null || $this->type !== null;
    }

    /**
     * Parametri della query string per questi filtri (solo quelli attivi), gli stessi
     * nomi del catalogo: `q`, `region`, `type`.
     *
     * @return array<string, string>
     */
    public function queryParameters(): array
    {
        return array_filter([
            'q' => $this->search,
            'region' => $this->region,
            'type' => $this->type?->value,
        ], static fn (?string $value): bool => $value !== null);
    }
}
