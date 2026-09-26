<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\OrganizationStatus;
use App\Models\Animal;
use App\Models\Organization;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\View as ViewFacade;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Profilo pubblico di un ente + griglia dei suoi animali adottabili.
 *
 * L'ente è legato per slug (`/enti/{organization:slug}`). Visibilità imposta
 * qui: 404 se l'ente non è verificato. La griglia usa
 * {@see Animal::publiclyVisible()} sulla relazione, così non trapelano bozze
 * anche quando (in teoria) l'ente fosse verificato.
 */
class OrganizationPage extends Component
{
    use WithPagination;

    public Organization $organization;

    public function mount(Organization $organization): void
    {
        abort_unless($organization->status === OrganizationStatus::Verified, 404);

        $this->organization = $organization;
    }

    public function render(): View
    {
        /** @var LengthAwarePaginator<int, Animal> $animals */
        $animals = $this->organization
            ->animals()
            ->publiclyVisible()
            ->with(['media', 'organization'])
            ->latest()
            ->paginate(12);

        $view = ViewFacade::make('livewire.organization-page', [
            'animals' => $animals,
        ]);

        $view->title($this->organization->name.' — ArkVel'); // @phpstan-ignore method.notFound

        return $view;
    }
}
