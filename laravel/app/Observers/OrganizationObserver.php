<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\Organizations\SyncOrganizationAnimalsSearchIndex;
use App\Models\Organization;

/**
 * Aggancia al cambio di stato di un ente (da qualunque percorso: pannello di
 * amministrazione, seeder, comando) il riallineamento dell'indice di ricerca dei suoi
 * animali ({@see SyncOrganizationAnimalsSearchIndex}): un ente sospeso sparisce subito dal
 * catalogo e dai conteggi delle faccette, uno verificato vi compare.
 *
 * Registrato via #[ObservedBy] sul model {@see Organization}. Gli altri aggiornamenti
 * dell'ente (nome, contatti, regione) non toccano l'indice.
 */
class OrganizationObserver
{
    public function __construct(
        private readonly SyncOrganizationAnimalsSearchIndex $syncSearchIndex,
    ) {}

    public function updated(Organization $organization): void
    {
        if ($organization->wasChanged('status')) {
            $this->syncSearchIndex->handle($organization);
        }
    }
}
