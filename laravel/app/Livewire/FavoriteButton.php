<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\Animals\FindFavoritedAnimalIds;
use App\Models\Animal;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View as ViewFacade;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Toggle "preferito" per un singolo animale, riusabile su card e scheda.
 *
 * Ownership: i preferiti sono SELF-OWNED. Il toggle opera SEMPRE ed
 * ESCLUSIVAMENTE sulla wishlist dell'utente autenticato
 * ({@see User::favorites()}), quindi non c'è alcuna azione cross-utente da
 * autorizzare a livello di riga: nessuna Policy sulla pivot. I due confini sono:
 *  - GUEST: non può mettere preferiti. Il toggle di un ospite lo devia alla pagina
 *    di accesso pubblica (`login`, /accedi), da cui potrà autenticarsi/registrarsi e
 *    tornare a scegliere.
 *  - SCOPING: attach/detach passano dalla relazione dell'utente corrente; lo
 *    stato mostrato riflette solo la sua wishlist.
 *
 * L'`animalId` è `#[Locked]`: è identità del componente, non deve essere
 * manomessa lato client.
 *
 * Stato iniziale senza query: in una griglia la pagina calcola UNA volta i preferiti
 * dell'utente tra i suoi animali ({@see FindFavoritedAnimalIds}) e li passa a ogni bottone
 * con `:favorited`. Senza il parametro (null) il bottone lo chiede da sé al database.
 * Lo stato passato vale solo per il primo render: dopo un toggle la verità è sempre la
 * pivot dell'utente autenticato, riletta dal database.
 *
 *     <livewire:favorite-button :animal="$animal" :favorited="in_array($animal->id, $favoriteIds, true)" :key="'fav-'.$animal->id" />
 */
class FavoriteButton extends Component
{
    #[Locked]
    public int $animalId;

    /**
     * Nome dell'animale, solo per il nome accessibile del bottone ("Salva Fido nei
     * preferiti", vedi x-ui.animal-card.favorite): in una griglia di card i bottoni
     * devono avere nomi distinti.
     */
    #[Locked]
    public string $animalName = '';

    /**
     * Già nei preferiti dell'utente autenticato. Dopo il mount è sempre un booleano: è
     * nullable solo perché Livewire assegna il parametro omonimo `favorited` (che può
     * valere null, «non noto») alla proprietà prima di chiamare mount().
     */
    public ?bool $favorited = false;

    /**
     * @param  bool|null  $favorited  stato già noto (calcolato dalla pagina per tutta la
     *                                griglia); null per leggerlo dal database
     */
    public function mount(Animal $animal, ?bool $favorited = null): void
    {
        $this->animalId = $animal->getKey();
        $this->animalName = $animal->name;
        $this->favorited = Auth::user() instanceof User && ($favorited ?? $this->resolveFavorited());
    }

    public function toggle(): mixed
    {
        $user = Auth::user();

        // Ospite: nessun preferito creato. Devia alla pagina di accesso pubblica.
        if (! $user instanceof User) {
            return $this->redirectRoute('login', navigate: true);
        }

        // Self-owned: toggle atomico sulla SOLA wishlist dell'utente corrente.
        // toggle() rispetta l'unique (user_id, animal_id): nessun duplicato.
        $user->favorites()->toggle($this->animalId);

        // Ricalcoliamo lo stato dal DB invece di invertire il flag alla cieca: la
        // UI riflette così lo stato REALE della pivot (robusto a race o a un flag
        // fuori sincrono col DB), non un'inversione presunta.
        $this->favorited = $user->favorites()->whereKey($this->animalId)->exists();

        return null;
    }

    public function render(): View
    {
        return ViewFacade::make('livewire.favorite-button');
    }

    /**
     * Stato iniziale: l'animale è già nella wishlist dell'utente autenticato?
     * Per un ospite è sempre `false` (non ha wishlist).
     */
    private function resolveFavorited(): bool
    {
        $user = Auth::user();

        return $user instanceof User
            && $user->favorites()->whereKey($this->animalId)->exists();
    }
}
