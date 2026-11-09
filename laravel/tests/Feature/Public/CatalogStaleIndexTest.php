<?php

declare(strict_types=1);

use App\Actions\Catalog\SearchCatalog;
use App\Data\Catalog\CatalogQuery;
use App\Models\Animal;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Scout\EngineManager;
use Meilisearch\Client;
use Meilisearch\Endpoints\Indexes;

/*
 | Catalogo con un indice Meilisearch rimasto indietro (client finto): documenti di animali
 | che non esistono più o di enti non verificati. La ricerca porta il vincolo degli enti
 | verificati (le pagine non si riempiono di fantasmi) e il totale «N trovati» è contato sul
 | database, quindi coincide con le card mostrate.
 */

uses(RefreshDatabase::class);

/**
 * Driver meilisearch con un indice finto che risponde sempre $response; le richieste
 * finiscono in $requests (parametri di ricerca).
 *
 * @param  array<string, mixed>  $response
 * @param  list<array<string, mixed>>  $requests
 */
function fakeStaleIndex(array $response, array &$requests): void
{
    $index = Mockery::mock(Indexes::class);
    $index->shouldReceive('rawSearch')->andReturnUsing(function (string $query, array $params) use ($response, &$requests): array {
        $requests[] = $params;

        return $response;
    });

    $client = Mockery::mock(Client::class);
    $client->shouldReceive('index')->with('animals')->andReturn($index);
    $client->shouldReceive('multiSearch')->andReturn(['results' => []]);

    app()->instance(Client::class, $client);
    config(['scout.driver' => 'meilisearch']);
    app(EngineManager::class)->forgetDrivers();
}

it('counts on the database only the animals it shows, ignoring the stale documents', function (): void {
    $verified = Organization::factory()->verified()->create();
    $suspended = Organization::factory()->create();
    $visible = Animal::factory()->count(2)->available()->forOrganization($verified)->create();
    $hidden = Animal::factory()->available()->forOrganization($suspended)->create();

    $requests = [];
    // Un documento di un animale cancellato (id 9999) e uno di un ente non verificato.
    fakeStaleIndex(['hits' => [['id' => 9999], ['id' => $visible[0]->id], ['id' => $hidden->id], ['id' => $visible[1]->id]], 'totalHits' => 4], $requests);

    $results = app(SearchCatalog::class)(new CatalogQuery);

    expect($results->total)->toBe(2)
        ->and($results->animals->pluck('id')->sort()->values()->all())->toBe($visible->pluck('id')->sort()->values()->all())
        ->and($requests[0]['filter'])->toContain('organization_id IN ['.$verified->id.']');
});

it('restricts the chosen organizations to the verified ones in the engine', function (): void {
    $verified = Organization::factory()->verified()->create(['slug' => 'aurora']);
    Animal::factory()->available()->forOrganization($verified)->create();

    $requests = [];
    fakeStaleIndex(['hits' => [], 'totalHits' => 0], $requests);

    app(SearchCatalog::class)(new CatalogQuery(organization: 'aurora'));

    // Un solo vincolo sull'ente (Scout ne tiene uno per attributo): quello scelto.
    expect($requests[0]['filter'])->toContain('organization_id IN ['.$verified->id.']');
});

it('does not ask the engine when no organization is verified', function (): void {
    Animal::factory()->available()->forOrganization(Organization::factory()->create())->create();

    $requests = [];
    fakeStaleIndex(['hits' => [], 'totalHits' => 0], $requests);

    $results = app(SearchCatalog::class)(new CatalogQuery);

    expect($results->total)->toBe(0)
        ->and($requests)->toBe([]);
});

it('gives only the total, without loading animals, photos or favorites, when the animals are not needed', function (): void {
    $organization = Organization::factory()->verified()->create();
    Animal::factory()->count(3)->available()->forOrganization($organization)->create();

    DB::enableQueryLog();

    $results = app(SearchCatalog::class)(new CatalogQuery(withAnimals: false));

    $queries = collect(DB::getQueryLog())->pluck('query');

    // Né foto né enti delle card (il driver collection dei test cerca sì sul database,
    // ma senza le relazioni della griglia).
    expect($results->total)->toBe(3)
        ->and($results->animals)->toBeEmpty()
        ->and($queries->filter(static fn (string $query): bool => str_contains($query, '"media"'))->all())->toBe([])
        ->and($queries->filter(static fn (string $query): bool => str_contains($query, 'from "organizations" where "organizations"."id" in'))->all())->toBe([]);
});
