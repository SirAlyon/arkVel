<?php

declare(strict_types=1);

namespace App\Support\Catalog;

/**
 * Un vincolo del catalogo sull'indice e sulla tabella `animals`: `field IN values`.
 * Tutti i campi sono colonne di `animals` e attributi filtrabili dell'indice
 * (config/scout.php), così lo stesso vincolo vale per Scout, Meilisearch e il database.
 * Una lista vuota non corrisponde a nessun animale.
 */
final readonly class CatalogConstraint
{
    /**
     * @param  list<int|string>  $values
     */
    public function __construct(
        public string $field,
        public array $values,
    ) {}

    public function matchesNothing(): bool
    {
        return $this->values === [];
    }
}
