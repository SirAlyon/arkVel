<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\Animals\FindFavoritedAnimalIds;
use App\Actions\Public\FindSimilarAnimals;
use App\Livewire\AnimalDetail\AdoptionApplicationForm;
use App\Livewire\AnimalDetail\AppointmentBooking;
use App\Livewire\AnimalDetail\ContactOrganizationForm;
use App\Livewire\AnimalDetail\FosterRequestForm;
use App\Models\Animal;
use App\Support\Seo\InteractsWithSeo;
use App\Support\Seo\PublicPageSeo;
use App\Support\Site\AnimalAge;
use App\Support\Site\TranslatedText;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View as ViewFacade;
use Livewire\Component;

/**
 * Scheda pubblica di un singolo animale.
 *
 * L'animale è legato per id (`/animali/{animal}`): lo slug è univoco solo
 * per-ente, quindi un binding globale su slug sarebbe ambiguo. La visibilità è
 * imposta qui — 404 se l'animale non è pubblicamente visibile
 * ({@see Animal::publiclyVisible()}): mai 200 per bozze o enti non verificati.
 *
 * La pagina è sottile: mostra la scheda (galleria, intestazione, schede informative,
 * ente, animali simili da {@see FindSimilarAnimals} con la card del sito) e la SEO; lo
 * stato dei preferiti (l'animale e i simili) arriva in una query sola da
 * {@see FindFavoritedAnimalIds}. Compone poi un componente
 * figlio per ogni CTA, ciascuno con il proprio stato e la propria Action:
 *  - {@see AdoptionApplicationForm}: candidatura all'adozione;
 *  - {@see FosterRequestForm}: richiesta di affido;
 *  - {@see AppointmentBooking}: prenotazione di un appuntamento;
 *  - {@see ContactOrganizationForm}: primo messaggio all'ente.
 *
 * I figli ricevono l'animale al mount e ne tengono solo l'id (`#[Locked]`); sono
 * indipendenti tra loro e dalla pagina, quindi non servono eventi.
 *
 * Pagina a token (UI-6c): segue il tema scelto (`themeable`). La pagina non ha azioni
 * proprie, quindi non si ri-renderizza mai: il pannello delle CTA può essere spostato nel
 * DOM (bottom sheet su mobile) senza che un morph lo rimetta al suo posto.
 */
class AnimalDetail extends Component
{
    use InteractsWithSeo;

    public Animal $animal;

    public function mount(Animal $animal): void
    {
        abort_unless(
            Animal::query()->publiclyVisible()->whereKey($animal->getKey())->exists(),
            404,
        );

        $animal->loadMissing(['media', 'organization']);

        $this->animal = $animal;
    }

    public function render(FindSimilarAnimals $findSimilar, FindFavoritedAnimalIds $findFavorites, PublicPageSeo $seo): View
    {
        $animal = $this->animal;
        $locale = app()->getLocale();
        $similarAnimals = $findSimilar->handle($animal);

        $view = ViewFacade::make('livewire.animal-detail', [
            'photos' => $animal->getMedia('photos'),
            'avatar' => $animal->getFirstMedia('avatar'),
            'age' => AnimalAge::of($animal),
            'locale' => $locale,
            'description' => TranslatedText::pick($animal, 'description', $locale),
            'organizationDescription' => TranslatedText::pick($animal->organization, 'description', $locale),
            'similarAnimals' => $similarAnimals,
            // Preferiti dell'utente tra l'animale e i simili: una query per tutta la pagina.
            'favoriteIds' => $findFavorites->handle(Auth::user(), [$animal, ...$similarAnimals->all()]),
        ]);
        $view->layoutData(['themeable' => true]); // @phpstan-ignore method.notFound

        return $this->withSeo($view, $seo->animal($animal));
    }
}
