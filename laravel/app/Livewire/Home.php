<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\Animals\FindFavoritedAnimalIds;
use App\Actions\Public\BuildHomeShowcase;
use App\Actions\Public\CountHomeStats;
use App\Actions\Public\FindHomeSpotlight;
use App\Enums\Species;
use App\Models\Animal;
use App\Support\Public\HomeShowcase;
use App\Support\Public\HomeStats;
use App\Support\Seo\InteractsWithSeo;
use App\Support\Seo\PublicPageSeo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View as ViewFacade;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Home pubblica (UI-6a, direzione B): hero a blocchi con ricerca verso il catalogo, numeri
 * chiave, vetrina «In cerca di casa» filtrabile per specie, come funziona l'adozione,
 * storie (segnaposto onesto) e inviti a sostenere gli enti.
 *
 * Componente sottile: i dati arrivano dalle Action di App\Actions\Public, che applicano
 * lo scope {@see Animal::publiclyVisible()} (l'unica fonte di verità della visibilità).
 * Sono proprietà calcolate, valutate solo dove la vista le usa: i chip della vetrina
 * aggiornano soltanto l'island `showcase`, quindi un cambio di filtro non ricalcola
 * né i numeri né la hero. L'animale della hero si sceglie una volta al mount e il suo id
 * resta bloccato ({@see self::$spotlightId}): la vetrina lo esclude senza cercarlo di nuovo.
 * I preferiti dell'utente tra gli animali della vetrina arrivano in una query sola.
 *
 * Opt-in di layout: `themeable` (segue il tema scelto) e `transparentHeader` (l'header
 * passa sopra la hero, che ne lascia lo spazio in alto).
 *
 * @property-read HomeStats $stats
 * @property-read Animal|null $spotlight
 * @property-read HomeShowcase $showcase
 * @property-read Species|null $selectedSpecies
 * @property-read list<int> $favoriteIds
 */
class Home extends Component
{
    use InteractsWithSeo;

    /**
     * Specie della vetrina (valore di {@see Species}); stringa vuota per tutte. Un valore
     * non valido dal client vale come «tutte» ({@see self::selectedSpecies()}).
     */
    public string $species = '';

    /**
     * L'animale della hero, scelto al mount: la vetrina lo esclude per non mostrarlo due
     * volte. Bloccato, perché il client non deve poter cambiare cosa si esclude.
     */
    #[Locked]
    public ?int $spotlightId = null;

    public function mount(): void
    {
        $spotlight = $this->spotlight;
        $this->spotlightId = $spotlight !== null ? (int) $spotlight->getKey() : null;
    }

    #[Computed]
    public function stats(): HomeStats
    {
        return app(CountHomeStats::class)->handle();
    }

    #[Computed]
    public function spotlight(): ?Animal
    {
        return app(FindHomeSpotlight::class)->handle();
    }

    #[Computed]
    public function showcase(): HomeShowcase
    {
        return app(BuildHomeShowcase::class)->handle(
            species: $this->selectedSpecies(),
            exceptAnimalId: $this->spotlightId,
        );
    }

    /**
     * @return list<int>
     */
    #[Computed]
    public function favoriteIds(): array
    {
        return app(FindFavoritedAnimalIds::class)->handle(Auth::user(), $this->showcase->animals);
    }

    #[Computed]
    public function selectedSpecies(): ?Species
    {
        return Species::tryFrom($this->species);
    }

    public function render(): View
    {
        $view = ViewFacade::make('livewire.home');
        $view->layoutData(['themeable' => true, 'transparentHeader' => true]); // @phpstan-ignore method.notFound

        return $this->withSeo($view, app(PublicPageSeo::class)->home());
    }
}
