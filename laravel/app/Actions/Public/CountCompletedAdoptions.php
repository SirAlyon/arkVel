<?php

declare(strict_types=1);

namespace App\Actions\Public;

use App\Actions\Adoptions\FinalizeAdoption;
use App\Actions\Organizations\SummarizeOrganizationAnimals;
use App\Models\Adoption;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Builder;

/**
 * Le «adozioni concluse» dei numeri pubblici: UNA definizione per la home
 * ({@see CountHomeStats}) e per la pagina di un ente ({@see SummarizeOrganizationAnimals}).
 *
 * Fonte: i record {@see Adoption}, cioè gli atti di adozione finalizzati sulla piattaforma
 * ({@see FinalizeAdoption}), presso enti verificati. Non lo stato `adopted` degli animali:
 * quello lo può impostare l'ente a mano (un'adozione avvenuta fuori dalla piattaforma) e
 * non conta due volte un animale adottato, restituito e riadottato. Solo un conteggio
 * aggregato: nessun dato personale (adottanti, date) esce dalla query.
 */
final readonly class CountCompletedAdoptions
{
    /**
     * @param  Organization|null  $organization  un solo ente; null per tutta la piattaforma
     */
    public function handle(?Organization $organization = null): int
    {
        return Adoption::query()
            ->when($organization !== null, static fn (Builder $query): Builder => $query->where('organization_id', $organization?->getKey()))
            ->whereHas('organization', static function (Builder $query): void {
                /** @var Builder<Organization> $query */
                $query->verified();
            })
            ->count();
    }
}
