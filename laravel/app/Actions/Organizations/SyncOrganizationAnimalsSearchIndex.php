<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Enums\OrganizationStatus;
use App\Models\Animal;
use App\Models\Organization;
use App\Observers\OrganizationObserver;
use Illuminate\Database\Eloquent\Collection;

/**
 * Riallinea l'indice di ricerca del catalogo agli animali di un ente quando cambia lo stato
 * dell'ente (verificato, in attesa, sospeso): la visibilità di un animale dipende anche
 * dall'ente ({@see Animal::shouldBeSearchable()}), ma l'observer di Scout scatta solo sul
 * salvataggio dell'animale. Invocata da {@see OrganizationObserver}.
 *
 * Per ogni animale dell'ente: nell'indice se deve esserlo (disponibile e di un ente
 * verificato), altrimenti tolto. Un ente sospeso sparisce quindi del tutto dall'indice;
 * uno verificato vi rientra con i soli animali disponibili.
 *
 * A blocchi (`scout.chunk.searchable`), e in coda se `scout.queue` è attivo.
 */
final class SyncOrganizationAnimalsSearchIndex
{
    public function handle(Organization $organization): void
    {
        $verified = $organization->status === OrganizationStatus::Verified;
        $index = new Animal;

        $organization->animals()->chunkById(
            (int) config('scout.chunk.searchable', 500),
            static function (Collection $animals) use ($verified, $index): void {
                /** @var Collection<int, Animal> $animals */
                $searchable = $verified
                    ? $animals->filter(static fn (Animal $animal): bool => $animal->shouldBeSearchable())->values()
                    : new Collection;

                $index->queueMakeSearchable($searchable);
                $index->queueRemoveFromSearch($animals->diff($searchable)->values());
            },
        );
    }
}
