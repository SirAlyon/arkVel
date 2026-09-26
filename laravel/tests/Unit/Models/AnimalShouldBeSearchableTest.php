<?php

declare(strict_types=1);

use App\Enums\AnimalStatus;
use App\Models\Animal;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('is searchable when available and owned by a verified organization', function () {
    $organization = Organization::factory()->verified()->create();
    $animal = Animal::factory()->available()->forOrganization($organization)->create();

    expect($animal->shouldBeSearchable())->toBeTrue();
});

it('is not searchable when the organization is not verified', function (callable $orgState) {
    $organization = $orgState(Organization::factory())->create();
    $animal = Animal::factory()->available()->forOrganization($organization)->create();

    expect($animal->shouldBeSearchable())->toBeFalse();
})->with([
    'pending' => [fn ($factory) => $factory->pending()],
    'suspended' => [fn ($factory) => $factory->suspended()],
]);

it('is not searchable when the animal is not available', function (AnimalStatus $status) {
    $organization = Organization::factory()->verified()->create();
    $animal = Animal::factory()
        ->forOrganization($organization)
        ->create(['status' => $status]);

    expect($animal->shouldBeSearchable())->toBeFalse();
})->with([
    'draft' => [AnimalStatus::Draft],
    'reserved' => [AnimalStatus::Reserved],
    'adoption pending' => [AnimalStatus::AdoptionPending],
    'adopted' => [AnimalStatus::Adopted],
    'fostered' => [AnimalStatus::Fostered],
    'medical hold' => [AnimalStatus::MedicalHold],
    'not available' => [AnimalStatus::NotAvailable],
]);

it('is not searchable once soft-deleted', function () {
    $organization = Organization::factory()->verified()->create();
    $animal = Animal::factory()->available()->forOrganization($organization)->create();

    $animal->delete();

    // Scout esclude di suo i trashed dall'indice; il predicato non deve mai
    // dichiarare ricercabile un animale rimosso.
    expect($animal->trashed())->toBeTrue()
        ->and($animal->shouldBeSearchable())->toBeFalse();
});
