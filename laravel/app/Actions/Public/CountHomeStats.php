<?php

declare(strict_types=1);

namespace App\Actions\Public;

use App\Models\Animal;
use App\Models\Organization;
use App\Support\Public\HomeStats;

/**
 * Numeri chiave della home pubblica, con gli stessi confini di visibilità del sito:
 * animali {@see Animal::publiclyVisible()}, enti verificati e adozioni concluse presso
 * enti verificati ({@see CountCompletedAdoptions}, la stessa definizione della pagina di un
 * ente: un ente sospeso o in verifica non contribuisce ai numeri pubblici).
 *
 * Solo conteggi aggregati: nessun dato personale (adottanti, date) esce dalla query.
 */
final readonly class CountHomeStats
{
    public function __construct(
        private CountCompletedAdoptions $countCompletedAdoptions,
    ) {}

    public function handle(): HomeStats
    {
        return new HomeStats(
            availableAnimals: Animal::query()->publiclyVisible()->count(),
            verifiedOrganizations: Organization::query()->verified()->count(),
            completedAdoptions: $this->countCompletedAdoptions->handle(),
        );
    }
}
