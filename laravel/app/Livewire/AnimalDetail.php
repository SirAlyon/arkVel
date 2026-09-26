<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Animal;
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
 */
class AnimalDetail extends Component
{
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
        // La CTA "candidati" porta all'area account (login/registrazione): per
        // questa slice il submit vero e proprio arriva in seguito. Vale sia per
        // ospite sia per utente loggato.
        $view = ViewFacade::make('livewire.animal-detail', [
            'photos' => $this->animal->getMedia('photos'),
            'applyUrl' => '/account',
        ]);

        // `title()` è una macro di Livewire sulla View (full-page component):
        // imposta il <title> passandolo al layout.
        $view->title($this->animal->name.' — ArkVel'); // @phpstan-ignore method.notFound

        return $view;
    }
}
