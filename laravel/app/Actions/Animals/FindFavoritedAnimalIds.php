<?php

declare(strict_types=1);

namespace App\Actions\Animals;

use App\Livewire\FavoriteButton;
use App\Models\Animal;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Quali degli animali di una pagina sono tra i preferiti dell'utente: UNA query per la
 * griglia intera, invece di una per card. Il risultato si passa a
 * {@see FavoriteButton} (`:favorited`), che così non interroga il database al mount.
 *
 * Self-owned come il toggle: legge solo la wishlist dell'utente indicato (di norma quello
 * autenticato). Per un ospite, o senza animali, nessuna query e nessun preferito.
 */
final class FindFavoritedAnimalIds
{
    /**
     * @param  iterable<int, Animal|int>  $animals  i Model o i loro id
     * @return list<int> gli id preferiti, nell'ordine crescente
     */
    public function handle(?Authenticatable $user, iterable $animals): array
    {
        $ids = [];

        foreach ($animals as $animal) {
            $ids[] = $animal instanceof Animal ? (int) $animal->getKey() : $animal;
        }

        if (! $user instanceof User || $ids === []) {
            return [];
        }

        return array_values(array_map(
            intval(...),
            $user->favorites()
                ->whereKey(array_values(array_unique($ids)))
                ->orderBy('animals.id')
                ->pluck('animals.id')
                ->all(),
        ));
    }
}
