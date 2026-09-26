<?php

declare(strict_types=1);

use App\Enums\AnimalStatus;
use App\Enums\EnergyLevel;
use App\Enums\Sex;
use App\Enums\Size;
use App\Enums\Species;
use App\Models\Animal;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('creates an animal from the factory', function () {
    $animal = Animal::factory()->create();

    expect($animal->exists)->toBeTrue()
        ->and($animal->status)->toBe(AnimalStatus::Draft);
});

it('casts the enum columns to their enum types', function () {
    $animal = Animal::factory()->create([
        'species' => Species::Dog,
        'sex' => Sex::Female,
        'size' => Size::Large,
        'status' => AnimalStatus::Available,
        'energy_level' => EnergyLevel::High,
    ]);

    $fresh = $animal->fresh();

    expect($fresh->species)->toBe(Species::Dog)
        ->and($fresh->sex)->toBe(Sex::Female)
        ->and($fresh->size)->toBe(Size::Large)
        ->and($fresh->status)->toBe(AnimalStatus::Available)
        ->and($fresh->energy_level)->toBe(EnergyLevel::High);
});

it('keeps good_with_* flags nullable to mean "unknown"', function () {
    $animal = Animal::factory()->create([
        'good_with_kids' => null,
        'good_with_dogs' => true,
        'good_with_cats' => false,
    ]);

    $fresh = $animal->fresh();

    expect($fresh->good_with_kids)->toBeNull()
        ->and($fresh->good_with_dogs)->toBeTrue()
        ->and($fresh->good_with_cats)->toBeFalse();
});

it('casts boolean health flags', function () {
    $animal = Animal::factory()->create([
        'sterilized' => true,
        'vaccinated' => false,
        'dewormed' => true,
        'application_paused' => false,
        'featured' => true,
    ]);

    $fresh = $animal->fresh();

    expect($fresh->sterilized)->toBeTrue()
        ->and($fresh->vaccinated)->toBeFalse()
        ->and($fresh->dewormed)->toBeTrue()
        ->and($fresh->application_paused)->toBeFalse()
        ->and($fresh->featured)->toBeTrue();
});

it('casts date columns', function () {
    $animal = Animal::factory()->create([
        'birthdate' => '2020-05-10',
        'intake_date' => '2023-01-15',
    ]);

    $fresh = $animal->fresh();

    expect($fresh->birthdate)->toBeInstanceOf(Carbon::class)
        ->and($fresh->birthdate->toDateString())->toBe('2020-05-10')
        ->and($fresh->intake_date->toDateString())->toBe('2023-01-15');
});

it('stores a translatable description in it and en', function () {
    $animal = Animal::factory()->create([
        'description' => ['it' => 'Cane dolcissimo', 'en' => 'Very sweet dog'],
    ]);

    $fresh = $animal->fresh();

    expect($fresh->getTranslation('description', 'it'))->toBe('Cane dolcissimo')
        ->and($fresh->getTranslation('description', 'en'))->toBe('Very sweet dog');
});

it('belongs to an organization', function () {
    $organization = Organization::factory()->create();
    $animal = Animal::factory()->forOrganization($organization)->create();

    expect($animal->organization)->toBeInstanceOf(Organization::class)
        ->and($animal->organization->getKey())->toBe($organization->getKey());
});

it('generates a slug from the name when none is provided', function () {
    $animal = Animal::factory()->create(['name' => 'Rex', 'slug' => null]);

    expect($animal->slug)->toBe('rex');
});

it('respects an explicitly provided slug', function () {
    $animal = Animal::factory()->create(['name' => 'Rex', 'slug' => 'custom-slug']);

    expect($animal->slug)->toBe('custom-slug');
});

it('deduplicates slugs within the same organization', function () {
    $organization = Organization::factory()->create();

    $first = Animal::factory()->forOrganization($organization)->create(['name' => 'Rex', 'slug' => null]);
    $second = Animal::factory()->forOrganization($organization)->create(['name' => 'Rex', 'slug' => null]);
    $third = Animal::factory()->forOrganization($organization)->create(['name' => 'Rex', 'slug' => null]);

    expect($first->slug)->toBe('rex')
        ->and($second->slug)->toBe('rex-2')
        ->and($third->slug)->toBe('rex-3');
});

it('deduplicates against soft-deleted records occupying the slug', function () {
    // Un record soft-deleted continua a occupare lo slug nell'indice unique del
    // DB (che ignora deleted_at). La deduplica deve quindi vederlo e ripiegare
    // su `rex-2` anche se il global scope SoftDeletingScope lo nasconderebbe.
    $organization = Organization::factory()->create();

    $first = Animal::factory()->forOrganization($organization)->create(['name' => 'Rex', 'slug' => null]);
    expect($first->slug)->toBe('rex');

    $first->delete();

    $second = Animal::factory()->forOrganization($organization)->create(['name' => 'Rex', 'slug' => null]);

    expect($second->slug)->toBe('rex-2');
});

it('allows the same slug in different organizations', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();

    $a = Animal::factory()->forOrganization($orgA)->create(['name' => 'Rex', 'slug' => null]);
    $b = Animal::factory()->forOrganization($orgB)->create(['name' => 'Rex', 'slug' => null]);

    expect($a->slug)->toBe('rex')
        ->and($b->slug)->toBe('rex');
});

it('soft deletes rather than hard deleting', function () {
    $animal = Animal::factory()->create();

    $animal->delete();

    expect(Animal::query()->count())->toBe(0)
        ->and(Animal::withTrashed()->count())->toBe(1)
        ->and($animal->fresh()->trashed())->toBeTrue();
});

it('scopes to available animals', function () {
    Animal::factory()->available()->count(2)->create();
    Animal::factory()->draft()->count(3)->create();

    expect(Animal::query()->available()->count())->toBe(2);
});

it('scopes to featured animals', function () {
    Animal::factory()->featured()->count(2)->create();
    Animal::factory()->create(['featured' => false]);

    expect(Animal::query()->featured()->count())->toBe(2);
});

it('attaches an image to the photos media collection', function () {
    // Disk fittizio: la collection `photos` usa il disk di default della
    // media-library (`public`). Evita side-effect sul filesystem reale.
    Storage::fake('public');

    $animal = Animal::factory()->create();

    $animal->addMedia(UploadedFile::fake()->image('dog.jpg'))->toMediaCollection('photos');

    expect($animal->getMedia('photos'))->toHaveCount(1);
});
