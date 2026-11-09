<?php

declare(strict_types=1);

use App\Actions\Public\BuildHomeShowcase;
use App\Actions\Public\CountHomeStats;
use App\Actions\Public\FindHomeSpotlight;
use App\Enums\Species;
use App\Models\Adoption;
use App\Models\AdoptionApplication;
use App\Models\Animal;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/*
 | Dati della home pubblica (UI-6a): le Action di App\Actions\Public che alimentano
 | App\Livewire\Home. Rendering e interazione: HomeTest; nel browser: tests/Browser/HomeTest.
 */

uses(RefreshDatabase::class);

function homeAnimal(Organization $organization, array $attributes = [], bool $featured = false): Animal
{
    return Animal::factory()->available()->forOrganization($organization)->create(['featured' => $featured, ...$attributes]);
}

function withHomePhoto(Animal $animal, string $collection = 'photos'): Animal
{
    $animal->addMedia(UploadedFile::fake()->image($animal->name.'.jpg', 600, 400))->toMediaCollection($collection);

    return $animal;
}

beforeEach(function (): void {
    Storage::fake('public');
    Queue::fake();
    $this->organization = Organization::factory()->verified()->create();
});

describe('CountHomeStats', function (): void {
    it('counts only what the public site shows', function (): void {
        homeAnimal($this->organization);
        homeAnimal($this->organization);
        Animal::factory()->draft()->forOrganization($this->organization)->create();
        Animal::factory()->available()->forOrganization(Organization::factory()->pending()->create())->create();
        Organization::factory()->suspended()->create();

        $stats = app(CountHomeStats::class)->handle();

        expect($stats->availableAnimals)->toBe(2)
            ->and($stats->verifiedOrganizations)->toBe(1)
            ->and($stats->completedAdoptions)->toBe(0)
            ->and($stats->hasCompletedAdoptions())->toBeFalse();
    });

    it('counts the completed adoptions of verified organizations only', function (): void {
        $verifiedAnimal = Animal::factory()->adopted()->forOrganization($this->organization)->create();
        Adoption::factory()->forApplication(AdoptionApplication::factory()->adoptionPending()->forAnimal($verifiedAnimal)->create())->create();

        $suspended = Organization::factory()->suspended()->create();
        $suspendedAnimal = Animal::factory()->adopted()->forOrganization($suspended)->create();
        Adoption::factory()->forApplication(AdoptionApplication::factory()->adoptionPending()->forAnimal($suspendedAnimal)->create())->create();

        $stats = app(CountHomeStats::class)->handle();

        expect($stats->completedAdoptions)->toBe(1)
            ->and($stats->hasCompletedAdoptions())->toBeTrue();
    });
});

describe('FindHomeSpotlight', function (): void {
    it('returns null when no visible animal has a photo', function (): void {
        homeAnimal($this->organization, featured: true);
        withHomePhoto(Animal::factory()->draft()->forOrganization($this->organization)->create());

        expect(app(FindHomeSpotlight::class)->handle())->toBeNull();
    });

    it('prefers a featured animal with a photo, with media and organization loaded', function (): void {
        withHomePhoto(homeAnimal($this->organization, ['name' => 'Recente']));
        $featured = withHomePhoto(homeAnimal($this->organization, ['name' => 'Stella', 'created_at' => now()->subWeek()], featured: true));
        homeAnimal($this->organization, ['name' => 'SenzaFoto'], featured: true);

        $spotlight = app(FindHomeSpotlight::class)->handle();

        expect($spotlight?->is($featured))->toBeTrue()
            ->and($spotlight?->relationLoaded('media'))->toBeTrue()
            ->and($spotlight?->relationLoaded('organization'))->toBeTrue();
    });

    it('accepts the avatar when the animal has no gallery photo', function (): void {
        $animal = withHomePhoto(homeAnimal($this->organization), 'avatar');

        expect(app(FindHomeSpotlight::class)->handle()?->is($animal))->toBeTrue();
    });

    it('never picks an animal of an unverified organization', function (): void {
        withHomePhoto(Animal::factory()->available()->featured()->forOrganization(Organization::factory()->pending()->create())->create());

        expect(app(FindHomeSpotlight::class)->handle())->toBeNull();
    });
});

describe('BuildHomeShowcase', function (): void {
    it('shows featured animals first, then the latest, up to the limit', function (): void {
        $old = homeAnimal($this->organization, ['name' => 'Vecchio', 'created_at' => now()->subYear()]);
        homeAnimal($this->organization, ['name' => 'Nuovo', 'created_at' => now()]);
        homeAnimal($this->organization, ['name' => 'Evidenza', 'created_at' => now()->subMonth()], featured: true);
        homeAnimal($this->organization, ['name' => 'Medio', 'created_at' => now()->subWeek()]);

        $showcase = app(BuildHomeShowcase::class)->handle();

        expect($showcase->animals->pluck('name')->all())->toBe(['Evidenza', 'Nuovo', 'Medio'])
            ->and($showcase->animals)->toHaveCount(BuildHomeShowcase::LIMIT)
            ->and($showcase->remaining)->toBe(1)
            ->and($showcase->species)->toBeNull()
            ->and($showcase->catalogQuery())->toBe([])
            ->and($showcase->isEmpty())->toBeFalse()
            ->and($showcase->animals->contains($old))->toBeFalse()
            ->and($showcase->animals->first()->relationLoaded('media'))->toBeTrue()
            ->and($showcase->animals->first()->relationLoaded('organization'))->toBeTrue();
    });

    it('filters by species and builds the matching catalog query', function (): void {
        homeAnimal($this->organization, ['name' => 'Rex', 'species' => Species::Dog]);
        homeAnimal($this->organization, ['name' => 'Micia', 'species' => Species::Cat]);

        $showcase = app(BuildHomeShowcase::class)->handle(Species::Cat);

        expect($showcase->animals->pluck('name')->all())->toBe(['Micia'])
            ->and($showcase->remaining)->toBe(0)
            ->and($showcase->species)->toBe(Species::Cat)
            ->and($showcase->catalogQuery())->toBe(['species' => 'cat']);
    });

    it('leaves out the spotlight animal, also from the remaining count', function (): void {
        $spotlight = homeAnimal($this->organization, ['name' => 'Luna'], featured: true);
        homeAnimal($this->organization, ['name' => 'Argo']);

        $showcase = app(BuildHomeShowcase::class)->handle(exceptAnimalId: $spotlight->id);

        expect($showcase->animals->pluck('name')->all())->toBe(['Argo'])
            ->and($showcase->remaining)->toBe(0);
    });

    it('never shows drafts or animals of unverified organizations', function (): void {
        Animal::factory()->draft()->featured()->forOrganization($this->organization)->create();
        Animal::factory()->available()->featured()->forOrganization(Organization::factory()->pending()->create())->create();

        $showcase = app(BuildHomeShowcase::class)->handle();

        expect($showcase->isEmpty())->toBeTrue()
            ->and($showcase->remaining)->toBe(0);
    });
});
