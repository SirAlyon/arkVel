<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\Organizations\ListOrganizationDirectoryFacets;
use App\Actions\Organizations\ListPublicOrganizations;
use App\Data\Organizations\OrganizationDirectoryFilters;
use App\Support\Seo\InteractsWithSeo;
use App\Support\Seo\PublicPageSeo;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\View as ViewFacade;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Elenco pubblico degli enti verificati (`/enti`), con il conteggio dei rispettivi
 * animali adottabili e i filtri per nome, regione e tipo di ente, tutti in query string
 * con gli stessi nomi del catalogo (`q`, `region`, `type`: un indirizzo filtrato si può
 * condividere). I vecchi nomi italiani (`regione`, `tipo`) valgono ancora in entrata, così
 * i link già condivisi funzionano; l'indirizzo si aggiorna con i nomi nuovi.
 * Senza JavaScript i filtri sono un modulo GET verso /enti.
 *
 * Componente sottile: normalizza i filtri in {@see OrganizationDirectoryFilters} e delega
 * la query a {@see ListPublicOrganizations} (solo enti `Verified`) e le opzioni dei filtri
 * a {@see ListOrganizationDirectoryFacets}. Cambiare un filtro riporta alla prima pagina.
 */
class OrganizationIndex extends Component
{
    use InteractsWithSeo;
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $region = '';

    #[Url(except: '')]
    public string $type = '';

    /**
     * Nomi della query string usati prima dell'allineamento al catalogo: nuovo => vecchio.
     */
    public const array LEGACY_PARAMETERS = ['region' => 'regione', 'type' => 'tipo'];

    public function mount(): void
    {
        foreach (self::LEGACY_PARAMETERS as $property => $legacy) {
            $value = request()->query($legacy);

            if ($this->{$property} === '' && is_string($value)) {
                $this->{$property} = $value;
            }
        }
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'region', 'type'], true)) {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset('search', 'region', 'type');
        $this->resetPage();
    }

    public function render(
        ListPublicOrganizations $listOrganizations,
        ListOrganizationDirectoryFacets $listFacets,
        PublicPageSeo $seo,
    ): View {
        $facets = $listFacets->handle();
        $filters = OrganizationDirectoryFilters::fromInput($this->search, $this->region, $this->type, $facets->regions);

        $organizations = $listOrganizations->handle($filters)->appends($filters->queryParameters());

        $view = ViewFacade::make('livewire.organization-index', [
            'organizations' => $organizations,
            'facets' => $facets,
            'filters' => $filters,
        ]);
        $view->layoutData(['themeable' => true]); // @phpstan-ignore method.notFound

        return $this->withSeo($view, $seo->organizations($organizations->currentPage(), $organizations->lastPage(), $filters->isFiltered()));
    }
}
