<?php

declare(strict_types=1);

namespace App\Data\Organizations;

use App\Actions\Organizations\SummarizeOrganizationAnimals;

/**
 * Numeri pubblici di un ente ({@see SummarizeOrganizationAnimals}): animali in adozione
 * (visibili nel catalogo) e adozioni concluse. Solo conteggi aggregati, nessun dato
 * personale.
 */
final readonly class OrganizationAnimalSummary
{
    public function __construct(
        public int $available,
        public int $adopted,
    ) {}
}
