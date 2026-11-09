<?php

declare(strict_types=1);

use App\Actions\Catalog\SearchCatalog;
use App\Actions\Map\BuildCatalogMap;
use App\Data\Catalog\CatalogQuery;
use App\Enums\Species;
use App\Models\Animal;
use App\Models\Organization;
use App\Support\Map\CatalogMap;
use App\Support\Map\MapMarker;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 | Dati della vista «Mappa» del catalogo (BuildCatalogMap sopra SearchCatalog, driver Scout
 | `collection` dei test): un segnaposto per ente con animali che corrispondono ai filtri,
 | solo enti verificati e animali pubblici, posizioni approssimate, link filtrati.
 */

uses(RefreshDatabase::class);

function catalogMap(CatalogQuery $query = new CatalogQuery): CatalogMap
{
    return app(BuildCatalogMap::class)(app(SearchCatalog::class)($query));
}

/**
 * @param  array<string, mixed>  $attributes
 */
function mapOrganization(string $slug, array $attributes = []): Organization
{
    return Organization::factory()->verified()->create([
        'slug' => $slug,
        'name' => 'Rifugio '.ucfirst($slug),
        'city' => 'Roma',
        'region' => 'Lazio',
        'latitude' => 41.8902102,
        'longitude' => 12.4922309,
        ...$attributes,
    ]);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function mapAnimals(Organization $organization, int $count = 1, array $attributes = []): void
{
    Animal::factory()->count($count)->available()->forOrganization($organization)->create($attributes);
}

/**
 * @return array<string, int> slug => animali
 */
function markerCounts(CatalogMap $map): array
{
    return array_column(array_map(static fn (MapMarker $marker): array => [$marker->id, $marker->count], $map->markers), 1, 0);
}

it('lists one marker per verified organization with public animals, by name', function (): void {
    mapAnimals(mapOrganization('stella'), 2);
    mapAnimals(mapOrganization('aurora'), 3);

    $map = catalogMap();

    expect(markerCounts($map))->toBe(['aurora' => 3, 'stella' => 2])
        ->and($map->markers[0])->toMatchObject([
            'title' => 'Rifugio Aurora',
            'description' => 'Roma, Lazio',
            'countLabel' => '3 animali',
            'url' => route('catalog', ['organization' => 'aurora']),
        ]);
});

it('never shows organizations that are not verified, nor animals that are not public', function (): void {
    mapAnimals(mapOrganization('aurora'));
    mapAnimals(mapOrganization('sospeso', ['status' => Organization::factory()->suspended()->raw()['status']]), 2);
    $pending = Organization::factory()->pending()->create(['slug' => 'attesa']);
    mapAnimals($pending);
    $drafts = mapOrganization('bozze');
    Animal::factory()->draft()->forOrganization($drafts)->create();
    Animal::factory()->adopted()->forOrganization($drafts)->create();

    expect(markerCounts(catalogMap()))->toBe(['aurora' => 1]);
});

it('follows the search and the filters of the catalog', function (): void {
    $aurora = mapOrganization('aurora');
    mapAnimals($aurora, 2, ['species' => Species::Dog]);
    mapAnimals($aurora, 1, ['species' => Species::Cat]);
    mapAnimals(mapOrganization('gattile'), 4, ['species' => Species::Cat]);
    mapAnimals(mapOrganization('nord', ['region' => 'Lombardia', 'city' => 'Milano']), 1, ['species' => Species::Dog]);
    Animal::factory()->available()->forOrganization($aurora)->create(['name' => 'Briciola', 'species' => Species::Cat]);

    expect(markerCounts(catalogMap(new CatalogQuery(species: 'dog'))))->toBe(['aurora' => 2, 'nord' => 1])
        ->and(markerCounts(catalogMap(new CatalogQuery(species: 'cat', region: 'Lazio'))))->toBe(['aurora' => 2, 'gattile' => 4])
        ->and(markerCounts(catalogMap(new CatalogQuery(search: 'Briciola'))))->toBe(['aurora' => 1]);
});

it('keeps only the chosen organization when the catalog is filtered by one', function (): void {
    mapAnimals(mapOrganization('aurora'), 2, ['species' => Species::Dog]);
    mapAnimals(mapOrganization('stella'), 3, ['species' => Species::Dog]);
    mapAnimals(mapOrganization('nord', ['region' => 'Lombardia', 'city' => 'Milano']));

    expect(markerCounts(catalogMap(new CatalogQuery(organization: 'stella'))))->toBe(['stella' => 3])
        ->and(markerCounts(catalogMap(new CatalogQuery(organization: 'stella', species: 'cat'))))->toBe([])
        // Ente fuori dalla regione scelta: nessun risultato, nessun segnaposto.
        ->and(catalogMap(new CatalogQuery(organization: 'stella', region: 'Lombardia'))->isEmpty())->toBeTrue();
});

it('links each marker to the catalog filtered by the organization, keeping the other filters', function (): void {
    mapAnimals(mapOrganization('aurora'), 1, ['species' => Species::Dog]);

    $marker = catalogMap(new CatalogQuery(species: 'dog', sort: 'name'))->markers[0];

    expect($marker->url)->toBe(route('catalog', ['species' => 'dog', 'sort' => 'name', 'organization' => 'aurora']));
});

it('publishes only the approximate position and keeps the organizations without one for the list', function (): void {
    mapAnimals(mapOrganization('aurora'));
    mapAnimals(mapOrganization('senza', ['latitude' => null, 'longitude' => null]));

    $map = catalogMap();
    [$aurora, $unlocated] = $map->markers;

    expect($aurora->location?->toArray())->toBe(['lat' => 41.89, 'lng' => 12.49, 'radius' => 1600])
        ->and($unlocated->id)->toBe('senza')
        ->and($unlocated->isLocated())->toBeFalse()
        ->and($map->located())->toBe([$aurora])
        ->and(json_encode(array_map(static fn (MapMarker $marker): array => $marker->toArray(), $map->markers)))
        ->not->toContain('41.89021')
        ->not->toContain('12.49223');
});

it('is empty without results', function (): void {
    mapAnimals(mapOrganization('aurora'), 1, ['species' => Species::Dog]);

    expect(catalogMap(new CatalogQuery(species: 'cat'))->isEmpty())->toBeTrue()
        ->and(catalogMap(new CatalogQuery(search: 'nessuno-si-chiama-cosi'))->isEmpty())->toBeTrue();
});

it('translates the number of animals', function (): void {
    mapAnimals(mapOrganization('aurora'));
    app()->setLocale('en');

    expect(catalogMap()->markers[0]->countLabel)->toBe('1 animal');
});
