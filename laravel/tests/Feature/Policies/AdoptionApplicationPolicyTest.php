<?php

declare(strict_types=1);

use App\Actions\Organizations\AddOrganizationMember;
use App\Enums\MembershipRole;
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
function applicationMemberOf(Organization $organization, MembershipRole $role): User
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

it('lets the adopter view and withdraw their own application', function () {
    $adopter = User::factory()->create();
    $application = AdoptionApplication::factory()->forAdopter($adopter)->create();

    expect($adopter->can('view', $application))->toBeTrue()
        ->and($adopter->can('withdraw', $application))->toBeTrue();
});

it('denies the adopter view/withdraw on someone else application', function () {
    $adopter = User::factory()->create();
    $other = User::factory()->create();
    $application = AdoptionApplication::factory()->forAdopter($other)->create();

    expect($adopter->can('view', $application))->toBeFalse()
        ->and($adopter->can('withdraw', $application))->toBeFalse();
});

it('lets org staff view and update applications of their own organization', function () {
    $org = Organization::factory()->create();
    $staff = applicationMemberOf($org, MembershipRole::Staff);
    $animal = Animal::factory()->forOrganization($org)->create();
    $application = AdoptionApplication::factory()->forAnimal($animal)->forOrganization($org)->create();

    expect($staff->can('viewAny', AdoptionApplication::class))->toBeTrue()
        ->and($staff->can('view', $application))->toBeTrue()
        ->and($staff->can('update', $application))->toBeTrue();
});

it('denies staff of another organization any per-application action (tenant isolation)', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();
    $staffA = applicationMemberOf($orgA, MembershipRole::Staff);
    $animalB = Animal::factory()->forOrganization($orgB)->create();
    $applicationB = AdoptionApplication::factory()->forAnimal($animalB)->forOrganization($orgB)->create();

    expect($staffA->can('view', $applicationB))->toBeFalse()
        ->and($staffA->can('update', $applicationB))->toBeFalse();
});

it('lets volunteers view but not update applications', function () {
    $org = Organization::factory()->create();
    $volunteer = applicationMemberOf($org, MembershipRole::Volunteer);
    $animal = Animal::factory()->forOrganization($org)->create();
    $application = AdoptionApplication::factory()->forAnimal($animal)->forOrganization($org)->create();

    expect($volunteer->can('view', $application))->toBeTrue()
        ->and($volunteer->can('update', $application))->toBeFalse();
});

it('never allows deleting an application', function () {
    $org = Organization::factory()->create();
    $owner = applicationMemberOf($org, MembershipRole::Owner);
    $animal = Animal::factory()->forOrganization($org)->create();
    $application = AdoptionApplication::factory()->forAnimal($animal)->forOrganization($org)->create();

    expect($owner->can('delete', $application))->toBeFalse();
});

it('lets any authenticated user viewAny and create', function () {
    $user = User::factory()->create();

    expect($user->can('viewAny', AdoptionApplication::class))->toBeTrue()
        ->and($user->can('create', AdoptionApplication::class))->toBeTrue();
});
