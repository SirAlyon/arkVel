<?php

declare(strict_types=1);

namespace App\Filament\Adopter\Pages;

use App\Models\Animal;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Pagina "I miei preferiti" del pannello adottante.
 *
 * Wishlist SELF-OWNED: la pagina mostra ESCLUSIVAMENTE i preferiti dell'utente
 * autenticato ({@see User::favorites()}) e la rimozione stacca SOLO la sua riga
 * di pivot. Non esiste azione cross-utente: nessuna Policy sulla pivot,
 * l'ownership è implicita nella scope della relazione. L'autorizzazione della
 * pagina è quella del pannello (qualunque adottante autenticato, con email
 * verificata: garanzia data dall'emailVerification() del pannello).
 *
 * Pagina sottile: la query è solo `Auth::user()->favorites()->with(...)`; la
 * rimozione è un `detach` sulla relazione dell'utente corrente. Nessuna business
 * logic.
 *
 * @property-read Collection<int, Animal> $favorites
 */
class Favorites extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected string $view = 'filament.adopter.pages.favorites';

    public static function getNavigationLabel(): string
    {
        return __('public.favorites.title');
    }

    public function getTitle(): string
    {
        return __('public.favorites.title');
    }

    /**
     * Preferiti dell'utente corrente, dai più recenti. Eager loading di media e
     * ente per thumbnail, specie/taglia e località senza query N+1. Risolto
     * sempre da Auth::user(): scoping per ownership.
     *
     * @return Collection<int, Animal>
     */
    public function getFavoritesProperty(): Collection
    {
        /** @var User $user */
        $user = Auth::user();

        return $user->favorites()
            ->with(['media', 'organization'])
            ->orderByPivot('created_at', 'desc')
            ->get();
    }

    /**
     * Rimuove un animale dalla wishlist dell'utente CORRENTE. `detach` opera solo
     * sulla riga di pivot dell'utente autenticato: nessun effetto sui preferiti
     * altrui. Il render successivo rilegge la lista aggiornata.
     */
    public function removeFavorite(int $animalId): void
    {
        /** @var User $user */
        $user = Auth::user();

        $user->favorites()->detach($animalId);
    }
}
