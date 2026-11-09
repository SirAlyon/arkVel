<?php

declare(strict_types=1);

namespace App\Data\Organizations;

use App\Actions\Organizations\ListOrganizationDirectoryFacets;
use App\Enums\OrganizationType;

/**
 * Valori disponibili per i filtri dell'indice degli enti
 * ({@see ListOrganizationDirectoryFacets}): solo quelli presenti tra gli enti verificati,
 * così nessun filtro porta a una pagina vuota per costruzione.
 */
final readonly class OrganizationDirectoryFacets
{
    /**
     * @param  list<string>  $regions  regioni in ordine alfabetico
     * @param  list<OrganizationType>  $types  tipi nell'ordine dell'enum
     */
    public function __construct(
        public array $regions = [],
        public array $types = [],
    ) {}
}
