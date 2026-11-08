<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Animal;
use App\Models\Organization;
use App\Support\Seo\InteractsWithSeo;
use App\Support\Seo\PublicPageSeo;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\View as ViewFacade;
use Livewire\Component;

/**
 * Homepage pubblica: hero, animali in evidenza (solo visibili pubblicamente),
 * come funziona l'adozione e qualche numero.
 *
 * Componente sottile: nessuna business logic, solo lettura gated dallo scope
 * {@see Animal::publiclyVisible()} — l'unica fonte di verità della visibilità.
 */
class Home extends Component
{
    use InteractsWithSeo;

    public function render(): View
    {
        /** @var Collection<int, Animal> $featured */
        $featured = Animal::query()
            ->publiclyVisible()
            ->with(['media', 'organization'])
            ->featured()
            ->latest()
            ->take(4)
            ->get();

        // Se non ci sono abbastanza animali in evidenza, riempi con altri
        // animali visibili per non mostrare mai una strip semivuota.
        if ($featured->count() < 4) {
            $fillers = Animal::query()
                ->publiclyVisible()
                ->with(['media', 'organization'])
                ->whereNotIn('id', $featured->modelKeys())
                ->latest()
                ->take(4 - $featured->count())
                ->get();

            $featured = $featured->concat($fillers);
        }

        $view = ViewFacade::make('livewire.home', [
            'featuredAnimals' => $featured,
            'availableCount' => Animal::query()->publiclyVisible()->count(),
            'organizationCount' => Organization::query()->verified()->count(),
        ]);

        return $this->withSeo($view, app(PublicPageSeo::class)->home());
    }
}
