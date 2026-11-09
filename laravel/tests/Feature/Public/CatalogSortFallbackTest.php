<?php

declare(strict_types=1);

use App\Actions\Catalog\SearchCatalog;
use App\Data\Catalog\CatalogQuery;
use App\Models\Animal;
use App\Models\Organization;
use App\Support\Catalog\CatalogScoutQuery;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Laravel\Scout\EngineManager;
use Meilisearch\Client;
use Meilisearch\Endpoints\Indexes;
use Meilisearch\Exceptions\ApiException;

/*
 | Catalogo sul driver Meilisearch di Scout (client finto) quando l'indice non ha ancora le
 | impostazioni di config/scout.php (manca `php artisan scout:sync-index-settings`):
 | l'ordinamento si degrada invece di dare 500, con un warning nel log.
 */

uses(RefreshDatabase::class);

function unsortable(string $attribute): ApiException
{
    return new ApiException(new Response(400), [
        'message' => "Attribute `{$attribute}` is not sortable.",
        'code' => CatalogScoutQuery::UNSORTABLE_ERROR,
        'type' => 'invalid_request',
    ]);
}

/**
 * Driver meilisearch con un client finto: l'indice degli animali è $index.
 */
function fakeMeilisearch(Indexes $index): void
{
    $client = Mockery::mock(Client::class);
    $client->shouldReceive('index')->with('animals')->andReturn($index);
    $client->shouldReceive('multiSearch')->andReturn(['results' => []]);

    app()->instance(Client::class, $client);
    config(['scout.driver' => 'meilisearch']);
    app(EngineManager::class)->forgetDrivers();
}

it('drops the featured order when the index cannot sort on it', function (): void {
    $organization = Organization::factory()->verified()->create();
    [$older, $newer] = [
        Animal::factory()->available()->forOrganization($organization)->create(['name' => 'Vecchio']),
        Animal::factory()->available()->forOrganization($organization)->create(['name' => 'Nuovo']),
    ];

    $index = Mockery::mock(Indexes::class);
    $index->shouldReceive('rawSearch')->once()->ordered()
        ->withArgs(fn (string $query, array $params): bool => $params['sort'] === ['featured:desc', 'created_at:desc'])
        ->andThrow(unsortable('featured'));
    $index->shouldReceive('rawSearch')->once()->ordered()
        ->withArgs(fn (string $query, array $params): bool => $params['sort'] === ['created_at:desc'])
        ->andReturn(['hits' => [['id' => $newer->id], ['id' => $older->id]], 'totalHits' => 2]);
    fakeMeilisearch($index);
    Log::spy();

    $results = app(SearchCatalog::class)(new CatalogQuery);

    expect($results->total)->toBe(2)
        ->and($results->animals->pluck('name')->all())->toBe(['Nuovo', 'Vecchio']);

    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context): bool => str_contains($message, 'scout:sync-index-settings')
        && $context['fallback'] === ['created_at' => 'desc']);
});

it('falls back to the relevance of the engine when no attribute is sortable', function (): void {
    Animal::factory()->available()->forOrganization(Organization::factory()->verified()->create())->create(['name' => 'Argo']);

    $index = Mockery::mock(Indexes::class);
    $index->shouldReceive('rawSearch')->once()->ordered()->withArgs(fn (string $query, array $params): bool => $params['sort'] === ['name:asc'])->andThrow(unsortable('name'));
    $index->shouldReceive('rawSearch')->once()->ordered()->withArgs(fn (string $query, array $params): bool => ! array_key_exists('sort', $params))->andReturn(['hits' => [], 'totalHits' => 0]);
    fakeMeilisearch($index);

    expect(app(SearchCatalog::class)(new CatalogQuery(sort: 'name'))->total)->toBe(0);
});

it('does not hide the other errors of the engine', function (): void {
    $index = Mockery::mock(Indexes::class);
    $index->shouldReceive('rawSearch')->once()->andThrow(new ApiException(new Response(500), ['message' => 'Boom', 'code' => 'internal']));
    fakeMeilisearch($index);

    app(SearchCatalog::class)(new CatalogQuery);
})->throws(ApiException::class, 'Boom');

it('lists the orders to try, from the requested one to none', function (): void {
    expect(CatalogScoutQuery::degradedOrders(['featured' => 'desc', 'created_at' => 'desc']))
        ->toBe([['featured' => 'desc', 'created_at' => 'desc'], ['created_at' => 'desc'], []])
        ->and(CatalogScoutQuery::degradedOrders(['name' => 'asc']))->toBe([['name' => 'asc'], []])
        ->and(CatalogScoutQuery::degradedOrders([]))->toBe([[]]);
});
