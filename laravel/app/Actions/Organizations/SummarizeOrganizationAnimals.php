<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Actions\Public\CountCompletedAdoptions;
use App\Data\Organizations\OrganizationAnimalSummary;
use App\Models\Animal;
use App\Models\Organization;

/**
 * Numeri della pagina pubblica di un ente: animali in adozione (lo stesso confine di
 * visibilità del catalogo, {@see Animal::publiclyVisible()}) e adozioni concluse
 * ({@see CountCompletedAdoptions}, la stessa definizione dei numeri della home).
 */
final readonly class SummarizeOrganizationAnimals
{
    public function __construct(
        private CountCompletedAdoptions $countCompletedAdoptions,
    ) {}

    public function handle(Organization $organization): OrganizationAnimalSummary
    {
        return new OrganizationAnimalSummary(
            available: $organization->animals()->publiclyVisible()->count(),
            adopted: $this->countCompletedAdoptions->handle($organization),
        );
    }
}
