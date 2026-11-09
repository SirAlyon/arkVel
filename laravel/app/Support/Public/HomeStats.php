<?php

declare(strict_types=1);

namespace App\Support\Public;

use App\Actions\Public\CountHomeStats;

/**
 * Numeri chiave della home ({@see CountHomeStats}): conteggi reali, mai stime.
 *
 * - `availableAnimals`: animali pubblicamente visibili (disponibili, di enti verificati);
 * - `verifiedOrganizations`: enti verificati;
 * - `completedAdoptions`: adozioni concluse presso enti verificati. Zero finché non ce ne
 *   sono: la pagina allora non mostra il numero.
 */
final readonly class HomeStats
{
    public function __construct(
        public int $availableAnimals,
        public int $verifiedOrganizations,
        public int $completedAdoptions,
    ) {}

    public function hasCompletedAdoptions(): bool
    {
        return $this->completedAdoptions > 0;
    }
}
