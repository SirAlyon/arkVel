<?php

declare(strict_types=1);

use App\Models\Animal;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\CollectionEngine;

/*
 | Regressione: i test scrivevano nell'indice Meilisearch di sviluppo (animali fantasma
 | nel catalogo locale). phpunit.xml forza il driver `collection` e un host irraggiungibile,
 | tests/TestCase.php lo ribadisce a ogni test (anche con la config in cache e nella suite
 | browser, che usa lo stesso TestCase).
 */

uses(RefreshDatabase::class);

it('never points the tests to the real search index', function (): void {
    expect(config('scout.driver'))->toBe('collection')
        ->and(config('scout.queue'))->toBeFalse()
        ->and(config('scout.meilisearch.host'))->toBe('http://127.0.0.1:9')
        ->and(app(EngineManager::class)->engine())->toBeInstanceOf(CollectionEngine::class);
});

it('indexes the saved animals on the test database only', function (): void {
    $animal = Animal::factory()->available()->forOrganization(Organization::factory()->verified()->create())->create(['name' => 'Fantasma']);

    expect(Animal::search('Fantasma')->keys()->all())->toBe([$animal->id]);
});
