<?php

declare(strict_types=1);

use App\Actions\Organizations\AddOrganizationMember;
use App\Enums\MembershipRole;
use App\Models\Adoption;
use App\Models\AdoptionApplication;
use App\Models\Animal;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

afterEach(function () {
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

/**
 * Membro verificato dell'ente col ruolo indicato, con il team id impostato sul
 * registrar così che i check `can()` valutino i permessi team-scoped dell'ente.
 */
function adoptionMemberOf(Organization $organization, MembershipRole $role): User
{
    $user = User::factory()->create();

    app(AddOrganizationMember::class)->handle(
        organization: $organization,
        user: $user,
        role: $role,
        verified: true,
    );

    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($organization->getKey());
    $registrar->forgetCachedPermissions();

    return $user;
}

/**
 * Adozione finalizzata per un animale dell'ente indicato, dall'adottante dato.
 */
function adoptionFor(Organization $organization, User $adopter): Adoption
{
    $animal = Animal::factory()->forOrganization($organization)->create();
    $application = AdoptionApplication::factory()
        ->forAnimal($animal)
        ->forAdopter($adopter)
        ->adoptionPending()
        ->create();

    return Adoption::factory()->forApplication($application)->create();
}

it('lets the adopter view their own adoption', function () {
    $organization = Organization::factory()->create();
    $adopter = User::factory()->create();
    $adoption = adoptionFor($organization, $adopter);

    expect($adopter->can('view', $adoption))->toBeTrue();
});

it('denies the adopter view on someone else adoption', function () {
    $organization = Organization::factory()->create();
    $adopter = User::factory()->create();
    $other = User::factory()->create();
    $adoption = adoptionFor($organization, $other);

    expect($adopter->can('view', $adoption))->toBeFalse();
});

it('lets org staff with ApplicationView view adoptions of their own organization', function () {
    $organization = Organization::factory()->create();
    $adopter = User::factory()->create();
    $adoption = adoptionFor($organization, $adopter);

    $staff = adoptionMemberOf($organization, MembershipRole::Staff);

    expect($staff->can('viewAny', Adoption::class))->toBeTrue()
        ->and($staff->can('view', $adoption))->toBeTrue();
});

it('denies staff of another organization viewing an adoption (tenant isolation)', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();
    $adopter = User::factory()->create();
    $adoptionB = adoptionFor($orgB, $adopter);

    $staffA = adoptionMemberOf($orgA, MembershipRole::Staff);

    expect($staffA->can('view', $adoptionB))->toBeFalse();
});

it('never allows create, update or delete of an adoption via policy', function () {
    $organization = Organization::factory()->create();
    $adopter = User::factory()->create();
    $adoption = adoptionFor($organization, $adopter);

    $owner = adoptionMemberOf($organization, MembershipRole::Owner);

    expect($owner->can('create', Adoption::class))->toBeFalse()
        ->and($owner->can('update', $adoption))->toBeFalse()
        ->and($owner->can('delete', $adoption))->toBeFalse();
});
