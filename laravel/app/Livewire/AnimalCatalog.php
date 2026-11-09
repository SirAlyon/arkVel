<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\Animals\FindFavoritedAnimalIds;
use App\Actions\Catalog\SearchCatalog;
use App\Actions\Map\BuildCatalogMap;
use App\Data\Catalog\CatalogQuery;
use App\Enums\CatalogFacet;
use App\Enums\CatalogSort;
use App\Enums\CatalogView;
use App\Support\Catalog\ActiveCatalogFilter;
use App\Support\Seo\InteractsWithSeo;
use App\Support\Seo\PublicPageSeo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View as ViewFacade;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Catalogo pubblico degli animali adottabili (UI-6b), componente sottile: tiene lo stato
 * della pagina (query string e pagine caricate) e delega la ricerca a {@see SearchCatalog}
 * (filtri validi, visibilità, faccette con i conteggi, chip, paginazione).
 *
 * - ricerca, filtri e ordinamento sono legati alla query string (`#[Url]`): link
 *   condivisibili, tasto «indietro», gli stessi parametri della home e della SEO
 *   (`q`, `species`, `sex`, `size`, `region`, `organization`, `sort`, `page`);
 * - `page` è la prima pagina mostrata; `pages` quante ne sono caricate di seguito
 *   («Carica altri» e caricamento continuo con wire:intersect), non va nell'URL;
 * - ogni cambio di ricerca, filtro o ordinamento riparte da pagina 1;
 * - i preferiti dell'utente tra gli animali mostrati arrivano in una sola query
 *   ({@see FindFavoritedAnimalIds}) e passano ai bottoni delle card;
 * - `display` (`?view=map`, UI-7) sceglie la vista: griglia degli animali o mappa degli
 *   enti con il suo elenco alternativo ({@see BuildCatalogMap}, calcolata solo in quella
 *   vista, dove animali e preferiti non si caricano: servono solo totale e faccette). I
 *   filtri restano gli stessi nelle due viste. Il cambio di vista entra nella cronologia
 *   (`history`): Indietro dalla mappa torna alla griglia.
 */
class AnimalCatalog extends Component
{
    use InteractsWithSeo;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $species = '';

    #[Url(except: '')]
    public string $sex = '';

    #[Url(except: '')]
    public string $size = '';

    #[Url(except: '')]
    public string $region = '';

    #[Url(except: '')]
    public string $organization = '';

    #[Url(except: '')]
    public string $sort = '';

    /** Vista del catalogo ({@see CatalogView}): `list` (predefinita) o `map`. */
    #[Url(as: 'view', history: true, except: 'list')]
    public string $display = 'list';

    /** Pagine caricate a partire da `page` (stato di vista, limitato da CatalogQuery). */
    public int $pages = 1;

    public function updated(string $property): void
    {
        if ($property !== 'page' && $property !== 'pages') {
            $this->restart();
        }
    }

    /**
     * Toglie un filtro attivo (chip): `q` per la ricerca o il valore di una faccetta.
     */
    public function removeFilter(string $key): void
    {
        if ($key === ActiveCatalogFilter::SEARCH) {
            $this->search = '';
        } elseif (($facet = CatalogFacet::tryFrom($key)) !== null) {
            $this->{$facet->value} = '';
        } else {
            return;
        }

        $this->restart();
    }

    /** Azzera ricerca e filtri (l'ordinamento resta). */
    public function resetFilters(): void
    {
        $this->reset('search', 'species', 'sex', 'size', 'region', 'organization');
        $this->restart();
    }

    public function loadMore(): void
    {
        $this->pages = min(CatalogQuery::MAX_PAGES, max(1, $this->pages) + 1);
    }

    public function render(SearchCatalog $searchCatalog, FindFavoritedAnimalIds $findFavorites, BuildCatalogMap $buildMap, PublicPageSeo $seo): View
    {
        $display = CatalogView::fromQuery($this->display);
        $mapView = $display === CatalogView::Map;

        $results = $searchCatalog(new CatalogQuery(
            search: $this->search,
            species: $this->species,
            sex: $this->sex,
            size: $this->size,
            region: $this->region,
            organization: $this->organization,
            sort: $this->sort,
            page: (int) $this->getPage(),
            pages: $this->pages,
            withAnimals: ! $mapView,
        ));

        $view = ViewFacade::make('livewire.animal-catalog', [
            'results' => $results,
            'catalogView' => $display,
            'map' => $mapView ? $buildMap($results) : null,
            'viewOptions' => $this->viewOptions($results->stateParameters()),
            // Preferiti dell'utente tra gli animali caricati: una query per tutta la griglia
            // (nessuna nella mappa, che non mostra animali).
            'favoriteIds' => $mapView ? [] : $findFavorites->handle(Auth::user(), $results->animals),
            'sortOptions' => array_column(array_map(
                static fn (CatalogSort $sort): array => ['value' => $sort === CatalogSort::Relevance ? '' : $sort->value, 'label' => $sort->label()],
                CatalogSort::cases(),
            ), 'label', 'value'),
        ]);
        $view->layoutData(['themeable' => true]); // @phpstan-ignore method.notFound

        return $this->withSeo($view, $seo->catalog(
            search: $results->filters->search,
            hasFacetFilters: $results->filters->hasFacetFilters() || $results->sort !== CatalogSort::Relevance,
            page: $results->firstPage,
            total: $results->total,
            lastPage: $results->lastPage(),
        ));
    }

    /**
     * Le voci del selettore «Elenco / Mappa»: link veri (senza JavaScript cambiano pagina)
     * con i filtri correnti.
     *
     * @param  array<string, string>  $state
     * @return array<string, array{label: string, icon: string, href: string}>
     */
    private function viewOptions(array $state): array
    {
        $options = [];

        foreach (CatalogView::cases() as $view) {
            $options[$view->value] = [
                'label' => $view->label(),
                'icon' => $view->icon(),
                'href' => route('catalog', [...$state, ...$view->queryParameters()]),
            ];
        }

        return $options;
    }

    private function restart(): void
    {
        $this->pages = 1;
        $this->resetPage();
    }
}
