<?php

declare(strict_types=1);

use App\Data\Organizations\OrganizationDirectoryFilters;
use App\Enums\OrganizationType;
use App\Http\Middleware\SetLocale;
use App\Livewire\OrganizationIndex;
use App\Models\Animal;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/*
 | Indice pubblico degli enti /enti (UI-6d): rendering IT/EN col tema, solo enti verificati,
 | filtri in query string (q, region, type; i vecchi regione e tipo valgono ancora), modulo GET
 | senza JavaScript, stati vuoti, paginazione che conserva i filtri, SEO delle ricerche.
 */

it('renders the index with the theme, in Italian and English', function (): void {
    Organization::factory()->verified()->create(['name' => 'Rifugio Il Faro', 'city' => 'Genova', 'region' => 'Liguria']);

    $this->get(route('organizations.index'))
        ->assertOk()
        ->assertDontSee('data-theme-forced', escape: false)
        ->assertSee('<h1 class="text-h1 text-fg">Enti verificati</h1>', escape: false)
        ->assertSee('Cerca per nome')
        ->assertSee('1 ente trovato')
        ->assertSee('Rifugio Il Faro')
        ->assertSee('Genova, Liguria')
        ->assertSee('data-organization-verified', escape: false)
        ->assertSee('wire:navigate.hover', escape: false);

    $this->withSession([SetLocale::SESSION_KEY => 'en'])->get(route('organizations.index'))
        ->assertOk()
        ->assertSee('Verified organizations')
        ->assertSee('Search by name')
        ->assertSee('1 organization found');
});

it('lists verified organizations only, also among the filter options', function (): void {
    Organization::factory()->verified()->create(['name' => 'Ente Verificato', 'region' => 'Toscana', 'type' => OrganizationType::Rifugio]);
    Organization::factory()->verified()->create(['name' => 'Altro Ente', 'region' => 'Lazio', 'type' => OrganizationType::Associazione]);
    Organization::factory()->pending()->create(['name' => 'Ente In Attesa', 'region' => 'Sardegna', 'type' => OrganizationType::Societa]);

    $this->get(route('organizations.index'))
        ->assertOk()
        ->assertSee('Ente Verificato')
        ->assertDontSee('Ente In Attesa')
        ->assertSee('<option value="Toscana"', escape: false)
        ->assertDontSee('<option value="Sardegna"', escape: false)
        ->assertSee('data-organization-type="rifugio"', escape: false)
        ->assertDontSee('data-organization-type="societa"', escape: false);
});

it('shows the number of publicly visible animals on each card', function (): void {
    $organization = Organization::factory()->verified()->create();
    Animal::factory()->available()->count(2)->forOrganization($organization)->create();
    Animal::factory()->draft()->forOrganization($organization)->create();

    $this->get(route('organizations.index'))->assertSee('2 animali disponibili');
});

it('filters by region, type and name from the query string', function (): void {
    Organization::factory()->verified()->create(['name' => 'Rifugio Toscano', 'region' => 'Toscana', 'type' => OrganizationType::Rifugio]);
    Organization::factory()->verified()->create(['name' => 'Canile Toscano', 'region' => 'Toscana', 'type' => OrganizationType::CanileComunale]);
    Organization::factory()->verified()->create(['name' => 'Rifugio Lombardo', 'region' => 'Lombardia', 'type' => OrganizationType::Rifugio]);

    $this->get(route('organizations.index', ['region' => 'Toscana']))
        ->assertSee('Rifugio Toscano')->assertSee('Canile Toscano')->assertDontSee('Rifugio Lombardo');

    $this->get(route('organizations.index', ['type' => 'rifugio']))
        ->assertSee('Rifugio Toscano')->assertSee('Rifugio Lombardo')->assertDontSee('Canile Toscano')
        ->assertSee('data-organization-type="rifugio"', escape: false);

    $this->get(route('organizations.index', ['q' => 'lombardo']))
        ->assertSee('Rifugio Lombardo')->assertDontSee('Rifugio Toscano');

    // Un tipo o una regione inventati non filtrano (e non rompono la pagina).
    $this->get(route('organizations.index', ['type' => 'inventato']))
        ->assertOk()->assertSee('3 enti trovati');
    $this->get(route('organizations.index', ['region' => 'Atlantide']))
        ->assertOk()->assertSee('3 enti trovati')->assertDontSee('data-organization-reset', escape: false);
});

it('still understands the old Italian parameter names of shared links', function (): void {
    Organization::factory()->verified()->create(['name' => 'Rifugio Toscano', 'region' => 'Toscana', 'type' => OrganizationType::Rifugio]);
    Organization::factory()->verified()->create(['name' => 'Canile Lombardo', 'region' => 'Lombardia', 'type' => OrganizationType::CanileComunale]);

    $this->get(route('organizations.index', ['regione' => 'Toscana']))
        ->assertSee('Rifugio Toscano')->assertDontSee('Canile Lombardo');

    Livewire::withQueryParams(['tipo' => 'canile_comunale'])
        ->test(OrganizationIndex::class)
        ->assertSet('type', 'canile_comunale')
        ->assertSee('Canile Lombardo')
        ->assertDontSee('Rifugio Toscano');

    // Il nome nuovo vince sul vecchio.
    Livewire::withQueryParams(['region' => 'Lombardia', 'regione' => 'Toscana'])
        ->test(OrganizationIndex::class)
        ->assertSet('region', 'Lombardia');
});

it('works without JavaScript: the filters are a GET form towards the index', function (): void {
    Organization::factory()->verified()->create(['region' => 'Toscana', 'type' => OrganizationType::Rifugio]);
    Organization::factory()->verified()->create(['region' => 'Lazio', 'type' => OrganizationType::Associazione]);

    $html = (string) $this->get(route('organizations.index', ['q' => 'faro', 'region' => 'Lazio', 'type' => 'rifugio']))->getContent();

    expect($html)
        ->toMatch('/<form method="get" action="'.preg_quote(route('organizations.index'), '/').'" wire:submit="\$refresh"/')
        ->toMatch('/<input\s+type="search"\s+id="organizations-search"\s+name="q"/')
        ->toContain('value="faro"')
        ->toContain('maxlength="'.OrganizationDirectoryFilters::MAX_SEARCH_LENGTH.'"')
        ->toMatch('/<select[^>]*id="organizations-region"[^>]*name="region"/')
        ->toMatch('/<noscript>.*name="type".*type="submit".*<\/noscript>/s');
});

it('keeps the searches out of the search engines', function (): void {
    Organization::factory()->verified()->create(['region' => 'Toscana']);

    $this->get(route('organizations.index'))
        ->assertDontSee('<meta name="robots" content="noindex', escape: false);

    $this->get(route('organizations.index', ['region' => 'Toscana']))
        ->assertSee('<meta name="robots" content="noindex', escape: false)
        ->assertSee('<link rel="canonical" href="'.route('organizations.index').'">', escape: false);
});

it('marks the active type chip as pressed', function (): void {
    Organization::factory()->verified()->create(['type' => OrganizationType::Rifugio]);
    Organization::factory()->verified()->create(['type' => OrganizationType::Associazione]);

    Livewire::withQueryParams(['type' => 'rifugio'])
        ->test(OrganizationIndex::class)
        ->assertSet('type', 'rifugio')
        ->assertSeeHtml('aria-pressed="true"')
        ->assertSeeHtmlInOrder(['aria-pressed="false"', 'data-organization-type=""']);
});

it('updates the results and goes back to the first page when a filter changes', function (): void {
    Organization::factory()->verified()->count(13)->create(['region' => 'Lazio']);
    Organization::factory()->verified()->create(['name' => 'Unico Veneto', 'region' => 'Veneto']);

    Livewire::withQueryParams(['page' => 2])
        ->test(OrganizationIndex::class)
        ->assertSet('paginators.page', 2)
        ->set('region', 'Veneto')
        ->assertSet('paginators.page', 1)
        ->assertSee('Unico Veneto')
        ->assertSee('1 ente trovato')
        ->assertSee('Azzera i filtri')
        ->set('search', 'nessun ente si chiama così')
        ->assertSee('Nessun ente corrisponde ai filtri')
        ->call('resetFilters')
        ->assertSet('search', '')
        ->assertSet('region', '')
        ->assertSet('type', '')
        ->assertSee('14 enti trovati');
});

it('keeps the filters in the pagination links', function (): void {
    Organization::factory()->verified()->count(13)->create(['region' => 'Lazio', 'type' => OrganizationType::Rifugio]);

    $this->get(route('organizations.index', ['region' => 'Lazio', 'type' => 'rifugio']))
        ->assertOk()
        ->assertSee('data-pagination', escape: false)
        ->assertSee('Pagina 1 di 2')
        ->assertSee('aria-current="page"', escape: false)
        ->assertSee('rel="next"', escape: false)
        ->assertSee('href="'.e(route('organizations.index', ['region' => 'Lazio', 'type' => 'rifugio', 'page' => 2])).'"', escape: false);
});

it('shows an empty state when there are no verified organizations', function (): void {
    Organization::factory()->pending()->create();

    $this->get(route('organizations.index'))
        ->assertOk()
        ->assertSee('Ancora nessun ente')
        ->assertSee('Non ci sono ancora enti verificati.')
        ->assertDontSee('data-organization-filters', escape: false);
});
