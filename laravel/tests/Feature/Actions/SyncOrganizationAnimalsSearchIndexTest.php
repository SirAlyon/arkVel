<?php

declare(strict_types=1);

use App\Actions\Organizations\SyncOrganizationAnimalsSearchIndex;
use App\Enums\OrganizationStatus;
use App\Models\Animal;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Scout\EngineManager;
use Laravel\Scout\Engines\NullEngine;

uses(RefreshDatabase::class);

/*
 | Indice di ricerca e stato dell'ente: quando un ente cambia stato i suoi animali entrano
 | o escono dall'indice (App\Observers\OrganizationObserver → SyncOrganizationAnimalsSearchIndex).
 | Un motore Scout finto registra gli id indicizzati e tolti.
 */

final class RecordingSearchEngine extends NullEngine
{
    /** @var list<int> */
    public array $indexed = [];

    /** @var list<int> */
    public array $removed = [];

    public function update($models): void
    {
        array_push($this->indexed, ...$models->map->getKey()->all());
    }

    public function delete($models): void
    {
        array_push($this->removed, ...$models->map->getKey()->all());
    }

    public function reset(): void
    {
        $this->indexed = [];
        $this->removed = [];
    }
}

beforeEach(function (): void {
    $engine = $this->engine = new RecordingSearchEngine;
    // Il manager lega la closure a sé stesso: niente $this dentro.
    app(EngineManager::class)->extend('recording', static fn (): RecordingSearchEngine => $engine);
    app(EngineManager::class)->forgetDrivers();
    config(['scout.driver' => 'recording', 'scout.queue' => false]);
});

it('removes every animal of an organization that gets suspended from the index', function (): void {
    $organization = Organization::factory()->verified()->create();
    $available = Animal::factory()->available()->count(2)->forOrganization($organization)->create();
    $draft = Animal::factory()->draft()->forOrganization($organization)->create();
    $other = Animal::factory()->available()->forOrganization(Organization::factory()->verified()->create())->create();
    $this->engine->reset();

    $organization->update(['status' => OrganizationStatus::Suspended]);

    expect($this->engine->removed)->toEqualCanonicalizing([...$available->modelKeys(), $draft->getKey()])
        ->not->toContain($other->getKey())
        ->and($this->engine->indexed)->toBe([]);
});

it('indexes the available animals of an organization that gets verified, and only those', function (): void {
    $organization = Organization::factory()->pending()->create();
    $available = Animal::factory()->available()->forOrganization($organization)->create();
    $adopted = Animal::factory()->adopted()->forOrganization($organization)->create();
    $this->engine->reset();

    $organization->update(['status' => OrganizationStatus::Verified]);

    expect($this->engine->indexed)->toBe([$available->getKey()])
        ->and($this->engine->removed)->toBe([$adopted->getKey()]);
});

it('leaves the index alone when other data of the organization change', function (): void {
    $organization = Organization::factory()->verified()->create();
    Animal::factory()->available()->forOrganization($organization)->create();
    $this->engine->reset();

    $organization->update(['name' => 'Nuovo nome', 'city' => 'Lucca']);

    expect($this->engine->indexed)->toBe([])->and($this->engine->removed)->toBe([]);
});

it('can be run directly for an organization', function (): void {
    $organization = Organization::factory()->suspended()->create();
    $animal = Animal::factory()->available()->forOrganization($organization)->create();
    $this->engine->reset();

    app(SyncOrganizationAnimalsSearchIndex::class)->handle($organization);

    expect($this->engine->removed)->toBe([$animal->getKey()]);
});
