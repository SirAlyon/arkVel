<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Livewire\AnimalDetail\AdoptionApplicationForm;
use App\Livewire\AnimalDetail\AppointmentBooking;
use App\Livewire\AnimalDetail\ContactOrganizationForm;
use App\Livewire\AnimalDetail\FosterRequestForm;
use App\Models\Animal;
use App\Support\Seo\InteractsWithSeo;
use App\Support\Seo\PublicPageSeo;
use Illuminate\Contracts\View\View;
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
 * La pagina è sottile: mostra la scheda (galleria, anagrafica, ente) e la SEO, e
 * compone un componente figlio per ogni CTA, ciascuno con il proprio stato e la propria
 * Action:
 *  - {@see AdoptionApplicationForm}: candidatura all'adozione;
 *  - {@see FosterRequestForm}: richiesta di affido;
 *  - {@see AppointmentBooking}: prenotazione di un appuntamento;
 *  - {@see ContactOrganizationForm}: primo messaggio all'ente.
 *
 * I figli ricevono l'animale al mount e ne tengono solo l'id (`#[Locked]`); sono
 * indipendenti tra loro e dalla pagina, quindi non servono eventi.
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

    public function render(): View
    {
        $view = ViewFacade::make('livewire.animal-detail', [
            'photos' => $this->animal->getMedia('photos'),
        ]);

        return $this->withSeo($view, app(PublicPageSeo::class)->animal($this->animal));
    }
}
