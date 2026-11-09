<?php

declare(strict_types=1);

namespace App\Actions\Public;

use App\Models\Animal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Animali "simili" da proporre in fondo alla scheda pubblica di un animale.
 *
 * Candidati: solo animali pubblicamente visibili ({@see Animal::publiclyVisible()}:
 * disponibili e di un ente verificato), mai quello corrente, e con almeno un legame con
 * lui: stessa specie oppure stesso ente. Ordine di pertinenza:
 *  1. stessa specie e stesso ente;
 *  2. stessa specie (altri enti);
 *  3. stesso ente (altre specie);
 * a parità, prima quelli in evidenza, poi i più recenti (id come spareggio stabile).
 *
 * Foto ed ente sono caricati in anticipo (card con foto e città, nessuna N+1).
 */
final class FindSimilarAnimals
{
    public const int DEFAULT_LIMIT = 4;

    /**
     * @return Collection<int, Animal>
     */
    public function handle(Animal $animal, int $limit = self::DEFAULT_LIMIT): Collection
    {
        if ($limit < 1) {
            return new Collection;
        }

        $species = $animal->species->value;
        $organizationId = $animal->organization_id;

        return Animal::query()
            ->publiclyVisible()
            ->whereKeyNot($animal->getKey())
            ->where(function (Builder $related) use ($species, $organizationId): void {
                $related->where('species', $species)
                    ->orWhere('organization_id', $organizationId);
            })
            ->orderByRaw(
                'case when species = ? and organization_id = ? then 0 when species = ? then 1 else 2 end',
                [$species, $organizationId, $species],
            )
            ->orderByDesc('featured')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->with(['media', 'organization'])
            ->limit($limit)
            ->get();
    }
}
