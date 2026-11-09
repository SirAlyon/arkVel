<?php

declare(strict_types=1);

use App\Enums\Species;
use App\Livewire\AnimalCatalog;
use App\Models\Animal;
use App\Models\Organization;
use App\Models\User;
use App\Support\Catalog\CatalogFilters;
use App\Support\Catalog\Contracts\CountsCatalogFacets;
use App\Support\Catalog\FacetCounts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
 | Vista «Mappa» del catalogo (UI-7): selettore «Elenco / Mappa» con stato nella query
 | string, mappa con i soli dati approssimati, elenco alternativo sempre presente,
 | attribuzione delle tile, stati senza posizione e senza risultati.
 */

uses(RefreshDatabase::class);

function seedMapCatalog(): Organization
{
    $organization = Organization::factory()->verified()->create([
        'slug' => 'aurora',
        'name' => 'Rifugio Aurora',
        'city' => 'Roma',
        'region' => 'Lazio',
        'address_line' => 'Via dei Mille 12',
        'postal_code' => '00185',
        'latitude' => 41.8902102,
        'longitude' => 12.4922309,
    ]);

    Animal::factory()->count(2)->available()->forOrganization($organization)->create(['species' => Species::Dog]);

    return $organization;
}

it('shows the grid by default, with a view switch made of real links', function (): void {
    seedMapCatalog();

    $this->get(route('catalog'))
        ->assertOk()
        ->assertSee('data-view-switch', escape: false)
        ->assertSee('role="group" aria-label="Vista"', escape: false)
        ->assertSee('href="'.e(route('catalog', ['view' => 'map'])).'"', escape: false)
        ->assertSeeInOrder(['aria-current="true"', 'data-view-switch-option="list"', 'Elenco', 'data-view-switch-option="map"', 'Mappa'], escape: false)
        ->assertSee('data-catalog-grid', escape: false)
        ->assertDontSee('data-ui-map', escape: false)
        ->assertDontSee('data-catalog-map', escape: false);
});

it('switches to the map with the query string and keeps the filters in the links', function (): void {
    seedMapCatalog();

    Livewire::withQueryParams(['species' => 'dog'])
        ->test(AnimalCatalog::class)
        ->assertSee('href="'.e(route('catalog', ['species' => 'dog', 'view' => 'map'])).'"', escape: false)
        ->set('display', 'map')
        ->assertSet('display', 'map')
        ->assertSee('data-catalog-map', escape: false)
        ->assertDontSee('data-catalog-grid', escape: false)
        ->assertDontSee('data-catalog-sort', escape: false)
        ->assertSee('href="'.e(route('catalog', ['species' => 'dog'])).'"', escape: false);
});

it('renders the map, the alternative list and the attribution, with approximate data only', function (): void {
    seedMapCatalog();

    $response = $this->get(route('catalog', ['view' => 'map']))->assertOk();

    $response->assertSeeInOrder(['data-catalog-map', 'Gli enti sulla mappa', 'data-ui-map', 'data-map-list', 'Rifugio Aurora', 'Roma, Lazio', '2 animali'], escape: false)
        ->assertSee('aria-labelledby="catalog-map-list-title"', escape: false)
        ->assertSee('href="'.e(route('catalog', ['organization' => 'aurora'])).'"', escape: false)
        ->assertSee('id="catalog-map-instructions"', escape: false)
        ->assertSee('https://www.openstreetmap.org/copyright', escape: false)
        ->assertSee('contributori di OpenStreetMap')
        ->assertSee('OpenMapTiles')
        ->assertSee('OpenFreeMap')
        ->assertSee('<noscript>', escape: false)
        ->assertSee('role="status"', escape: false)
        ->assertSee('&quot;lat&quot;:41.89,', escape: false)
        ->assertSee('&quot;lng&quot;:12.49,', escape: false)
        ->assertSee('tiles.openfreemap.org', escape: false);

    // Mai le coordinate salvate né l'indirizzo, in nessuna forma (markup, JSON, snapshot).
    $html = (string) $response->getContent();

    expect($html)->not->toContain('41.89021')
        ->not->toContain('12.49223')
        ->not->toContain('Via dei Mille')
        ->not->toContain('00185');
});

it('falls back to the grid for an unknown view', function (): void {
    seedMapCatalog();

    $this->get(route('catalog', ['view' => 'satellite']))
        ->assertOk()
        ->assertSee('data-catalog-grid', escape: false)
        ->assertDontSee('data-catalog-map', escape: false);
});

it('lists the organizations without a position, without a map when none has one', function (): void {
    $organization = seedMapCatalog();
    $organization->update(['latitude' => null, 'longitude' => null]);

    $this->get(route('catalog', ['view' => 'map']))
        ->assertOk()
        ->assertSee('data-catalog-map-list', escape: false)
        ->assertSee('Rifugio Aurora')
        ->assertSee('Posizione non disponibile')
        ->assertSee('data-catalog-map-unlocated', escape: false)
        ->assertDontSee('data-ui-map', escape: false);
});

it('shows the empty state instead of the map when nothing matches', function (): void {
    seedMapCatalog();

    $this->get(route('catalog', ['view' => 'map', 'species' => 'cat']))
        ->assertOk()
        ->assertSee('data-catalog-empty="filtered"', escape: false)
        ->assertDontSee('data-catalog-map', escape: false);
});

it('keeps the view in the forms that work without JavaScript', function (): void {
    seedMapCatalog();

    $this->get(route('catalog', ['view' => 'map']))
        ->assertOk()
        ->assertSee('<input type="hidden" name="view" value="map">', escape: false);
});

it('is translated in English', function (): void {
    seedMapCatalog();

    $this->withSession(['locale' => 'en'])
        ->get(route('catalog', ['view' => 'map']))
        ->assertOk()
        ->assertSee('Organizations on the map')
        ->assertSee('List of organizations')
        ->assertSee('2 animals')
        ->assertSee('OpenStreetMap contributors')
        ->assertSee('role="group" aria-label="View"', escape: false);
});

it('loads neither the animals nor the favorites in the map view', function (): void {
    $organization = seedMapCatalog();
    $user = User::factory()->create();
    $user->favorites()->attach($organization->animals()->value('id'));

    DB::enableQueryLog();

    $this->actingAs($user)->get(route('catalog', ['view' => 'map']))
        ->assertOk()
        ->assertSee('data-catalog-map', escape: false)
        ->assertSee('2 animali')
        ->assertSee('2 animali trovati');

    $queries = collect(DB::getQueryLog())->pluck('query');

    expect($queries->filter(static fn (string $query): bool => str_contains($query, '"favorites"'))->all())->toBe([])
        ->and($queries->filter(static fn (string $query): bool => str_contains($query, '"media"'))->all())->toBe([]);
});

it('shows a way back to the grid instead of an empty list when no organization can be shown', function (): void {
    seedMapCatalog();
    // Il riepilogo dice che ci sono animali, ma la faccetta «Ente» non ne conta nessuno
    // (es. un indice in ritardo): la mappa non ha enti da mostrare.
    app()->bind(CountsCatalogFacets::class, static fn (): CountsCatalogFacets => new class implements CountsCatalogFacets
    {
        public function count(CatalogFilters $filters): FacetCounts
        {
            return new FacetCounts;
        }
    });

    $this->get(route('catalog', ['view' => 'map']))
        ->assertOk()
        ->assertSee('data-catalog-empty="map"', escape: false)
        ->assertSee('Nessun ente da mostrare sulla mappa')
        ->assertSee('href="'.e(route('catalog')).'"', escape: false)
        ->assertDontSee('data-map-list', escape: false)
        ->assertDontSee('data-ui-map', escape: false);
});
