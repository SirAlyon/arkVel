<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\Sex;
use App\Enums\Size;
use App\Enums\Species;
use App\Models\Animal;
use App\Support\Seo\InteractsWithSeo;
use App\Support\Seo\PublicPageSeo;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\View as ViewFacade;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Catalogo pubblico degli animali adottabili.
 *
 * La ricerca full-text passa da Scout (Meilisearch in produzione, driver
 * `collection` nei test). La VISIBILITÀ è imposta a livello Eloquent tramite lo
 * scope {@see Animal::publiclyVisible()} nella callback `->query()` di Scout,
 * così vale sia per Meilisearch sia per il driver collection (difesa in
 * profondità): mai animali in bozza o di enti non verificati. I filtri di
 * faccetta viaggiano come `->where()` di Scout e sono legati alla query string
 * (`#[Url]`) per essere condivisibili e coerenti col tasto "indietro".
 */
class AnimalCatalog extends Component
{
    use InteractsWithSeo;
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $species = '';

    #[Url]
    public string $size = '';

    #[Url]
    public string $sex = '';

    public function updated(string $property): void
    {
        // Ogni cambio di ricerca/filtro riparte da pagina 1.
        if ($property !== 'page') {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'species', 'size', 'sex');
        $this->resetPage();
    }

    public function render(): View
    {
        $animals = $this->buildSearch();

        $view = ViewFacade::make('livewire.animal-catalog', [
            'animals' => $animals,
            'speciesOptions' => Species::cases(),
            'sizeOptions' => Size::cases(),
            'sexOptions' => Sex::cases(),
            'hasActiveFilters' => $this->hasActiveFilters(),
        ]);

        return $this->withSeo($view, app(PublicPageSeo::class)->catalog(
            search: $this->search,
            hasFacetFilters: $this->hasFacetFilters(),
            page: $animals->currentPage(),
            total: $animals->total(),
            lastPage: $animals->lastPage(),
        ));
    }

    /**
     * @return LengthAwarePaginator<int, Animal>
     */
    private function buildSearch(): LengthAwarePaginator
    {
        $builder = Animal::search(trim($this->search))
            // Visibilità + eager loading. La callback è onorata da Meilisearch
            // ed è la difesa-in-profondità che chiude ogni fuga cross-tenant.
            ->query(function (Builder $query): Builder {
                /** @var Builder<Animal> $query */
                return $query
                    ->publiclyVisible()
                    ->with(['media', 'organization']);
            });

        // Filtri di faccetta: `->where()` di Scout. Sul driver collection sono
        // applicati come where SQL; su Meilisearch come filtri sull'indice.
        if ($this->species !== '') {
            $builder->where('species', $this->species);
        }

        if ($this->size !== '') {
            $builder->where('size', $this->size);
        }

        if ($this->sex !== '') {
            $builder->where('sex', $this->sex);
        }

        /** @var LengthAwarePaginator<int, Animal> $paginator */
        $paginator = $builder->paginate(12);

        return $paginator;
    }

    private function hasActiveFilters(): bool
    {
        return $this->search !== '' || $this->hasFacetFilters();
    }

    /**
     * Specie, taglia o sesso attivi (la ricerca libera esclusa).
     */
    private function hasFacetFilters(): bool
    {
        return $this->species !== ''
            || $this->size !== ''
            || $this->sex !== '';
    }
}
