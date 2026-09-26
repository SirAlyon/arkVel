<?php

declare(strict_types=1);

use App\Enums\FosterApplicationStatus;
use App\Models\Animal;
use App\Models\FosterApplication;
use App\Models\Organization;
use App\Models\User;
use App\States\Foster\FosterApplicationState;
use App\States\Foster\Submitted;
use App\States\Foster\UnderReview;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('casts status to a FosterApplicationState instance and exposes the enum', function () {
    $application = FosterApplication::factory()->underReview()->create();

    expect($application->status)->toBeInstanceOf(FosterApplicationState::class)
        ->and($application->status)->toBeInstanceOf(UnderReview::class)
        ->and($application->statusEnum())->toBe(FosterApplicationStatus::UnderReview);
});

it('persists the enum string value in the status column', function () {
    $application = FosterApplication::factory()->underReview()->create();

    expect($application->getRawOriginal('status'))->toBe(FosterApplicationStatus::UnderReview->value);
});

it('defaults to Submitted when no status is provided', function () {
    $animal = Animal::factory()->create();
    $application = FosterApplication::create([
        'animal_id' => $animal->getKey(),
        'user_id' => User::factory()->create()->getKey(),
        'organization_id' => $animal->organization_id,
    ]);

    expect($application->status)->toBeInstanceOf(Submitted::class)
        ->and($application->statusEnum())->toBe(FosterApplicationStatus::Submitted);
});

it('casts submitted_at and decided_at to Carbon', function () {
    $application = FosterApplication::factory()->completed()->create();

    expect($application->submitted_at)->toBeInstanceOf(Carbon::class)
        ->and($application->decided_at)->toBeInstanceOf(Carbon::class);
});

it('keeps organization consistent with the animal org via the factory', function () {
    $application = FosterApplication::factory()->create();

    expect($application->organization_id)->toBe($application->animal->organization_id);
});

it('links to animal, foster carer, organization and assigned staff', function () {
    $organization = Organization::factory()->create();
    $animal = Animal::factory()->forOrganization($organization)->create();
    $carer = User::factory()->create();
    $staff = User::factory()->create();

    $application = FosterApplication::factory()
        ->forAnimal($animal)
        ->forAdopter($carer)
        ->create(['assigned_to' => $staff->getKey()]);

    expect($application->animal)->toBeInstanceOf(Animal::class)
        ->and($application->animal->is($animal))->toBeTrue()
        ->and($application->user->is($carer))->toBeTrue()
        ->and($application->organization->is($organization))->toBeTrue()
        ->and($application->assignedTo->is($staff))->toBeTrue();
});

it('allows a null assigned staff member', function () {
    $application = FosterApplication::factory()->create();

    expect($application->assigned_to)->toBeNull()
        ->and($application->assignedTo)->toBeNull();
});

it('enforces a unique (animal_id, user_id) pair at the database level', function () {
    $animal = Animal::factory()->create();
    $carer = User::factory()->create();

    FosterApplication::factory()->forAnimal($animal)->forAdopter($carer)->create();

    expect(fn () => FosterApplication::factory()->forAnimal($animal)->forAdopter($carer)->create())
        ->toThrow(QueryException::class);
});

it('allows the same carer to apply for different animals', function () {
    $carer = User::factory()->create();
    $first = Animal::factory()->create();
    $second = Animal::factory()->create();

    FosterApplication::factory()->forAnimal($first)->forAdopter($carer)->create();
    $secondApplication = FosterApplication::factory()->forAnimal($second)->forAdopter($carer)->create();

    expect($secondApplication->exists)->toBeTrue()
        ->and(FosterApplication::query()->where('user_id', $carer->getKey())->count())->toBe(2);
});

it('exposes the distinctive Italian labels for foster-specific states', function () {
    expect(FosterApplicationStatus::Active->getLabel())->toBe('Affido in corso')
        ->and(FosterApplicationStatus::Completed->getLabel())->toBe('Completato');
});
