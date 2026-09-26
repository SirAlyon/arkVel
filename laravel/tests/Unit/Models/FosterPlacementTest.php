<?php

declare(strict_types=1);

use App\Enums\FosterPlacementStatus;
use App\Enums\FosterPlacementType;
use App\Models\Animal;
use App\Models\FosterApplication;
use App\Models\FosterPlacement;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('casts type and status to the right enum instances', function () {
    $placement = FosterPlacement::factory()->ofType(FosterPlacementType::Medical)->create();

    expect($placement->type)->toBe(FosterPlacementType::Medical)
        ->and($placement->status)->toBe(FosterPlacementStatus::Active);
});

it('casts start_date and end_date to Carbon', function () {
    $placement = FosterPlacement::factory()->ended()->create();

    expect($placement->start_date)->toBeInstanceOf(Carbon::class)
        ->and($placement->end_date)->toBeInstanceOf(Carbon::class);
});

it('allows a null end_date while active', function () {
    $placement = FosterPlacement::factory()->active()->create();

    expect($placement->end_date)->toBeNull();
});

it('links to animal, foster carer, organization and origin application', function () {
    $organization = Organization::factory()->create();
    $animal = Animal::factory()->forOrganization($organization)->create();
    $carer = User::factory()->create();
    $application = FosterApplication::factory()
        ->forAnimal($animal)
        ->forAdopter($carer)
        ->approved()
        ->create();

    $placement = FosterPlacement::factory()->forApplication($application)->create();

    expect($placement->animal)->toBeInstanceOf(Animal::class)
        ->and($placement->animal->is($animal))->toBeTrue()
        ->and($placement->user->is($carer))->toBeTrue()
        ->and($placement->organization->is($organization))->toBeTrue()
        ->and($placement->fosterApplication)->toBeInstanceOf(FosterApplication::class)
        ->and($placement->fosterApplication->is($application))->toBeTrue();
});

it('allows a null origin application (direct/emergency placement)', function () {
    $placement = FosterPlacement::factory()->create();

    expect($placement->foster_application_id)->toBeNull()
        ->and($placement->fosterApplication)->toBeNull();
});

it('keeps organization consistent with the animal via the factory', function () {
    $placement = FosterPlacement::factory()->create();

    expect($placement->organization_id)->toBe($placement->animal->organization_id);
});

it('scopes active to only Active placements', function () {
    FosterPlacement::factory()->active()->count(2)->create();
    FosterPlacement::factory()->ended()->create();

    $active = FosterPlacement::query()->active()->get();

    expect($active)->toHaveCount(2)
        ->and($active->every(fn (FosterPlacement $p): bool => $p->status === FosterPlacementStatus::Active))->toBeTrue();
});

it('aligns animal, carer and organization to the origin application via forApplication()', function () {
    $organization = Organization::factory()->create();
    $animal = Animal::factory()->forOrganization($organization)->create();
    $carer = User::factory()->create();
    $application = FosterApplication::factory()
        ->forAnimal($animal)
        ->forAdopter($carer)
        ->create();

    $placement = FosterPlacement::factory()->forApplication($application)->create();

    expect($placement->foster_application_id)->toBe($application->getKey())
        ->and($placement->animal_id)->toBe($animal->getKey())
        ->and($placement->user_id)->toBe($carer->getKey())
        ->and($placement->organization_id)->toBe($organization->getKey());
});

it('marks the placement ended with a past end_date via ended()', function () {
    $placement = FosterPlacement::factory()->ended()->create();

    expect($placement->status)->toBe(FosterPlacementStatus::Ended)
        ->and($placement->end_date)->toBeInstanceOf(Carbon::class)
        ->and($placement->end_date->isPast())->toBeTrue();
});
