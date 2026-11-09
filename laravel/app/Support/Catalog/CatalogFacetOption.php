<?php

declare(strict_types=1);

namespace App\Support\Catalog;

/**
 * Un valore di una faccetta del catalogo, pronto per la vista: valore della query
 * string, etichetta tradotta, animali corrispondenti, scelto o no.
 */
final readonly class CatalogFacetOption
{
    public function __construct(
        public string $value,
        public string $label,
        public int $count,
        public bool $selected = false,
    ) {}
}
