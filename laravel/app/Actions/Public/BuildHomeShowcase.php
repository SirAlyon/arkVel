<?php

declare(strict_types=1);

namespace App\Actions\Public;

use App\Enums\Species;
use App\Models\Animal;
use App\Support\Public\HomeShowcase;
use Illuminate\Database\Eloquent\Builder;

/**
 * Vetrina «In cerca di casa» della home: fino a {@see self::LIMIT} animali pubblicamente
 * visibili ({@see Animal::publiclyVisible()}), prima quelli in evidenza e poi i più
 * recenti, eventualmente di una sola specie. Con la card finale del catalogo la griglia
 * fa una riga piena da 4 su desktop.
 *
 * `$exceptAnimalId` esclude l'animale già mostrato nella hero ({@see FindHomeSpotlight}),
 * per non ripeterlo due volte nella stessa schermata. `remaining` conta gli altri animali
 * della stessa selezione rimasti fuori dalla vetrina (e dalla hero): è il numero della card
 * «Sfoglia il catalogo».
 *
 * Due query in tutto (la lista con `media` e `organization`, e il conteggio): nessun N+1.
 */
final readonly class BuildHomeShowcase
{
    public const int LIMIT = 3;

    public function handle(?Species $species = null, ?int $exceptAnimalId = null): HomeShowcase
    {
        $animals = $this->selection($species, $exceptAnimalId)
            ->with(['media', 'organization'])
            ->orderByDesc('featured')
            ->latest()
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get();

        $total = $this->selection($species, $exceptAnimalId)->count();

        return new HomeShowcase(
            animals: $animals,
            remaining: max(0, $total - $animals->count()),
            species: $species,
        );
    }

    /**
     * @return Builder<Animal>
     */
    private function selection(?Species $species, ?int $exceptAnimalId): Builder
    {
        return Animal::query()
            ->publiclyVisible()
            ->when($species !== null, static fn (Builder $query): Builder => $query->where('species', $species))
            ->when($exceptAnimalId !== null, static fn (Builder $query): Builder => $query->whereKeyNot($exceptAnimalId));
    }
}
