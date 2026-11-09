<?php

declare(strict_types=1);

use App\Actions\Public\FindSimilarAnimals;
use App\Enums\Species;
use App\Models\Animal;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/** Nomi degli animali simili, nell'ordine restituito. */
function similarAnimalNames(Animal $animal, int $limit = FindSimilarAnimals::DEFAULT_LIMIT): array
{
    return app(FindSimilarAnimals::class)->handle($animal, $limit)->pluck('name')->all();
}

it('suggests animals of the same species or of the same organization, never the current one', function (): void {
    $shelter = Organization::factory()->verified()->create();
    $elsewhere = Organization::factory()->verified()->create();

    $argo = Animal::factory()->available()->forOrganization($shelter)->create(['name' => 'Argo', 'species' => Species::Dog]);
    Animal::factory()->available()->forOrganization($elsewhere)->create(['name' => 'Fido', 'species' => Species::Dog]);
    Animal::factory()->available()->forOrganization($shelter)->create(['name' => 'Micia', 'species' => Species::Cat]);
    Animal::factory()->available()->forOrganization($elsewhere)->create(['name' => 'Tom', 'species' => Species::Cat]);

    expect(similarAnimalNames($argo))->toEqualCanonicalizing(['Fido', 'Micia']);
});

it('only suggests publicly visible animals', function (): void {
    $shelter = Organization::factory()->verified()->create();
    $pending = Organization::factory()->pending()->create();
    $suspended = Organization::factory()->suspended()->create();

    $argo = Animal::factory()->available()->forOrganization($shelter)->create(['name' => 'Argo', 'species' => Species::Dog]);
    Animal::factory()->available()->forOrganization($shelter)->create(['name' => 'Visibile', 'species' => Species::Dog]);
    Animal::factory()->draft()->forOrganization($shelter)->create(['name' => 'Bozza', 'species' => Species::Dog]);
    Animal::factory()->adopted()->forOrganization($shelter)->create(['name' => 'Adottato', 'species' => Species::Dog]);
    Animal::factory()->available()->forOrganization($pending)->create(['name' => 'Ente in verifica', 'species' => Species::Dog]);
    Animal::factory()->available()->forOrganization($suspended)->create(['name' => 'Ente sospeso', 'species' => Species::Dog]);
    Animal::factory()->available()->forOrganization($shelter)->create(['name' => 'Eliminato', 'species' => Species::Dog])->delete();

    expect(similarAnimalNames($argo))->toBe(['Visibile']);
});

it('ranks same species and organization first, then same species, then same organization', function (): void {
    $shelter = Organization::factory()->verified()->create();
    $elsewhere = Organization::factory()->verified()->create();

    $argo = Animal::factory()->available()->forOrganization($shelter)->create(['name' => 'Argo', 'species' => Species::Dog]);
    // Creati in ordine inverso di pertinenza: l'ordine non viene dalla data (né da «in evidenza»).
    Animal::factory()->available()->forOrganization($shelter)->create(['name' => 'Stesso ente', 'species' => Species::Cat]);
    Animal::factory()->available()->forOrganization($elsewhere)->create(['name' => 'Stessa specie', 'species' => Species::Dog]);
    Animal::factory()->available()->forOrganization($shelter)->create(['name' => 'Entrambi', 'species' => Species::Dog]);

    expect(similarAnimalNames($argo))->toBe(['Entrambi', 'Stessa specie', 'Stesso ente']);
});

it('puts featured animals first and then the newest within the same rank', function (): void {
    $shelter = Organization::factory()->verified()->create();

    $argo = Animal::factory()->available()->forOrganization($shelter)->create(['name' => 'Argo', 'species' => Species::Dog]);
    Animal::factory()->available()->forOrganization($shelter)->create(['name' => 'Vecchio', 'species' => Species::Dog, 'featured' => false, 'created_at' => now()->subDays(10)]);
    Animal::factory()->available()->forOrganization($shelter)->create(['name' => 'Nuovo', 'species' => Species::Dog, 'featured' => false, 'created_at' => now()->subDay()]);
    Animal::factory()->available()->featured()->forOrganization($shelter)->create(['name' => 'In evidenza', 'species' => Species::Dog, 'created_at' => now()->subDays(20)]);

    expect(similarAnimalNames($argo))->toBe(['In evidenza', 'Nuovo', 'Vecchio']);
});

it('respects the limit and returns nothing for a non-positive one', function (): void {
    $shelter = Organization::factory()->verified()->create();

    $argo = Animal::factory()->available()->forOrganization($shelter)->create(['species' => Species::Dog]);
    Animal::factory()->count(6)->available()->forOrganization($shelter)->create(['species' => Species::Dog]);

    expect(app(FindSimilarAnimals::class)->handle($argo))->toHaveCount(FindSimilarAnimals::DEFAULT_LIMIT)
        ->and(app(FindSimilarAnimals::class)->handle($argo, 2))->toHaveCount(2)
        ->and(app(FindSimilarAnimals::class)->handle($argo, 0))->toBeEmpty();
});

it('returns an empty collection when nothing is related', function (): void {
    $shelter = Organization::factory()->verified()->create();
    $elsewhere = Organization::factory()->verified()->create();

    $argo = Animal::factory()->available()->forOrganization($shelter)->create(['species' => Species::Dog]);
    Animal::factory()->available()->forOrganization($elsewhere)->create(['species' => Species::Cat]);

    expect(similarAnimalNames($argo))->toBe([]);
});

it('eager loads photos and organization for the cards', function (): void {
    $shelter = Organization::factory()->verified()->create();

    $argo = Animal::factory()->available()->forOrganization($shelter)->create(['species' => Species::Dog]);
    Animal::factory()->count(3)->available()->forOrganization($shelter)->create(['species' => Species::Dog]);

    $similar = app(FindSimilarAnimals::class)->handle($argo);

    DB::enableQueryLog();
    $similar->each(function (Animal $animal): void {
        $animal->getFirstMedia('photos');
        $animal->organization->city;
    });

    expect($similar->every(fn (Animal $animal): bool => $animal->relationLoaded('media') && $animal->relationLoaded('organization')))->toBeTrue()
        ->and(DB::getQueryLog())->toBe([]);
});
