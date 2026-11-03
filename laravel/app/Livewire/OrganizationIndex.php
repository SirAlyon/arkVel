<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Animal;
use App\Models\Organization;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\View as ViewFacade;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Elenco pubblico degli enti verificati, con il conteggio dei rispettivi
 * animali adottabili. Solo enti `Verified`: nessuna fuga di enti pendenti o
 * sospesi.
 */
class OrganizationIndex extends Component
{
    use WithPagination;

    public function render(): View
    {
        /** @var LengthAwarePaginator<int, Organization> $organizations */
        $organizations = Organization::query()
            ->verified()
            ->withCount(['animals as available_animals_count' => function (Builder $query): void {
                /** @var Builder<Animal> $query */
                $query->publiclyVisible();
            }])
            ->orderBy('name')
            ->paginate(12);

        $view = ViewFacade::make('livewire.organization-index', [
            'organizations' => $organizations,
        ]);

        $view->title(__('public.titles.organizations')); // @phpstan-ignore method.notFound

        return $view;
    }
}
