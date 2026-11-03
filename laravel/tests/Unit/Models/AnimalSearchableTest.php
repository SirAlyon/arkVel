<?php

declare(strict_types=1);

use App\Enums\EnergyLevel;
use App\Enums\Sex;
use App\Enums\Size;
use App\Enums\Species;
use App\Models\Animal;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('uses a stable index name', function () {
    expect((new Animal)->searchableAs())->toBe('animals');
});

it('exposes only scalar values in the searchable document', function () {
    $organization = Organization::factory()->verified()->create([
        'country' => 'IT',
        'region' => 'Lombardia',
        'city' => 'Milano',
    ]);

    $animal = Animal::factory()
        ->available()
        ->forOrganization($organization)
        ->create([
            'name' => 'Rex',
            'species' => Species::Dog,
            'sex' => Sex::Male,
            'size' => Size::Large,
            'energy_level' => EnergyLevel::High,
            'breed' => 'Labrador',
            'featured' => true,
            'description' => ['it' => 'Cane socievole', 'en' => 'Friendly dog'],
        ]);

    $document = $animal->toSearchableArray();

    // Chiavi attese.
    expect($document)->toHaveKeys([
        'id', 'name', 'description', 'description_it', 'description_en',
        'species', 'sex', 'size', 'energy_level', 'breed', 'organization_id',
        'country', 'region', 'city', 'featured', 'created_at',
    ]);

    // Gli enum viaggiano come stringa (->value), non come oggetto.
    expect($document['species'])->toBe('dog')
        ->and($document['sex'])->toBe('male')
        ->and($document['size'])->toBe('large')
        ->and($document['energy_level'])->toBe('high')
        ->and($document['species'])->toBeString()
        ->and($document['sex'])->toBeString()
        ->and($document['size'])->toBeString();

    // Identita' e relazione.
    expect($document['id'])->toBe($animal->getKey())
        ->and($document['organization_id'])->toBe($organization->getKey())
        ->and($document['name'])->toBe('Rex')
        ->and($document['breed'])->toBe('Labrador')
        ->and($document['featured'])->toBeTrue();

    // Localita' copiata dall'ente.
    expect($document['country'])->toBe('IT')
        ->and($document['region'])->toBe('Lombardia')
        ->and($document['city'])->toBe('Milano');

    // Descrizione tradotta (locale di default = it) + entrambe le traduzioni.
    expect($document['description'])->toBe('Cane socievole')
        ->and($document['description_it'])->toBe('Cane socievole')
        ->and($document['description_en'])->toBe('Friendly dog');

    // Timestamp ordinabile come intero unix.
    expect($document['created_at'])->toBeInt()
        ->and($document['created_at'])->toBe($animal->created_at?->getTimestamp());
});

it('emits null for optional enums and missing translations without failing', function () {
    $organization = Organization::factory()->verified()->create();

    $animal = Animal::factory()
        ->available()
        ->forOrganization($organization)
        ->create([
            'size' => null,
            'energy_level' => null,
            'breed' => null,
        ]);

    $document = $animal->toSearchableArray();

    expect($document['size'])->toBeNull()
        ->and($document['energy_level'])->toBeNull()
        ->and($document['breed'])->toBeNull();
});
