<?php

declare(strict_types=1);

use App\Enums\ApplicationStatus;
use App\Models\AdoptionApplication;
use App\Models\Animal;
use App\Models\Organization;
use App\Models\User;
use App\States\Application\ApplicationState;
use App\States\Application\Submitted;
use App\States\Application\UnderReview;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('casts status to an ApplicationState instance and exposes the enum', function () {
    $application = AdoptionApplication::factory()->underReview()->create();

    expect($application->status)->toBeInstanceOf(ApplicationState::class)
        ->and($application->status)->toBeInstanceOf(UnderReview::class)
        ->and($application->statusEnum())->toBe(ApplicationStatus::UnderReview);
});

it('persists the enum string value in the status column', function () {
    $application = AdoptionApplication::factory()->underReview()->create();

    expect($application->getRawOriginal('status'))->toBe(ApplicationStatus::UnderReview->value);
});

it('defaults to Submitted when no status is provided', function () {
    // Il default DB è 'submitted' e il model applica la default state; verifichiamo
    // che una candidatura creata senza stato esplicito atterri su Submitted.
    $animal = Animal::factory()->create();
    $application = AdoptionApplication::create([
        'animal_id' => $animal->getKey(),
        'user_id' => User::factory()->create()->getKey(),
        'organization_id' => $animal->organization_id,
    ]);

    expect($application->status)->toBeInstanceOf(Submitted::class)
        ->and($application->statusEnum())->toBe(ApplicationStatus::Submitted);
});

it('casts submitted_at and decided_at to Carbon', function () {
    $application = AdoptionApplication::factory()->completed()->create();

    expect($application->submitted_at)->toBeInstanceOf(Carbon::class)
        ->and($application->decided_at)->toBeInstanceOf(Carbon::class);
});

it('keeps organization consistent with the animal org via the factory', function () {
    $application = AdoptionApplication::factory()->create();

    expect($application->organization_id)->toBe($application->animal->organization_id);
});

it('links to animal, adopter, organization and assigned staff', function () {
    $organization = Organization::factory()->create();
    $animal = Animal::factory()->forOrganization($organization)->create();
    $adopter = User::factory()->create();
    $staff = User::factory()->create();

    $application = AdoptionApplication::factory()
        ->forAnimal($animal)
        ->forAdopter($adopter)
        ->create(['assigned_to' => $staff->getKey()]);

    expect($application->animal)->toBeInstanceOf(Animal::class)
        ->and($application->animal->is($animal))->toBeTrue()
        ->and($application->user->is($adopter))->toBeTrue()
        ->and($application->organization->is($organization))->toBeTrue()
        ->and($application->assignedTo->is($staff))->toBeTrue();
});

it('allows a null assigned staff member', function () {
    $application = AdoptionApplication::factory()->create();

    expect($application->assigned_to)->toBeNull()
        ->and($application->assignedTo)->toBeNull();
});

it('enforces a unique (animal_id, user_id) pair at the database level', function () {
    $animal = Animal::factory()->create();
    $adopter = User::factory()->create();

    AdoptionApplication::factory()->forAnimal($animal)->forAdopter($adopter)->create();

    expect(fn () => AdoptionApplication::factory()->forAnimal($animal)->forAdopter($adopter)->create())
        ->toThrow(QueryException::class);
});

it('allows the same adopter to apply for different animals', function () {
    $adopter = User::factory()->create();
    $first = Animal::factory()->create();
    $second = Animal::factory()->create();

    AdoptionApplication::factory()->forAnimal($first)->forAdopter($adopter)->create();
    $secondApplication = AdoptionApplication::factory()->forAnimal($second)->forAdopter($adopter)->create();

    expect($secondApplication->exists)->toBeTrue()
        ->and(AdoptionApplication::query()->where('user_id', $adopter->getKey())->count())->toBe(2);
});
