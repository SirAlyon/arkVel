<?php

declare(strict_types=1);

namespace App\Support\Public;

use App\Actions\Public\BuildHomeShowcase;
use App\Enums\Species;
use App\Models\Animal;
use Illuminate\Database\Eloquent\Collection;

/**
 * Vetrina «In cerca di casa» della home ({@see BuildHomeShowcase}).
 *
 * - `animals`: gli animali da mostrare (con `media` e `organization` già caricati);
 * - `remaining`: quanti altri animali della stessa selezione restano nel catalogo, per la
 *   card finale «Sfoglia il catalogo»;
 * - `species`: la specie filtrata, null per tutte.
 */
final readonly class HomeShowcase
{
    /**
     * @param  Collection<int, Animal>  $animals
     */
    public function __construct(
        public Collection $animals,
        public int $remaining,
        public ?Species $species,
    ) {}

    public function isEmpty(): bool
    {
        return $this->animals->isEmpty();
    }

    /**
     * Parametri della query string del catalogo per la stessa selezione (`species`, come
     * i filtri di App\Livewire\AnimalCatalog).
     *
     * @return array<string, string>
     */
    public function catalogQuery(): array
    {
        return $this->species === null ? [] : ['species' => $this->species->value];
    }
}
