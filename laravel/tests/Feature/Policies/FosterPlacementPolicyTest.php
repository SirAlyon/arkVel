<?php

declare(strict_types=1);

use App\Actions\Organizations\AddOrganizationMember;
use App\Enums\MembershipRole;
use App\Models\Animal;
use App\Models\FosterPlacement;
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
function placementMemberOf(Organization $organization, MembershipRole $role): User
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

it('lets the carer view their own placement', function () {
    $carer = User::factory()->create();
    $placement = FosterPlacement::factory()->forCarer($carer)->create();

    expect($carer->can('view', $placement))->toBeTrue();
});

it('denies the carer view on someone else placement', function () {
    $carer = User::factory()->create();
    $other = User::factory()->create();
    $placement = FosterPlacement::factory()->forCarer($other)->create();

    expect($carer->can('view', $placement))->toBeFalse();
});

it('lets verified org staff view and update placements of their own organization', function () {
    $org = Organization::factory()->create();
    $staff = placementMemberOf($org, MembershipRole::Staff);
    $animal = Animal::factory()->forOrganization($org)->create();
    $placement = FosterPlacement::factory()->forAnimal($animal)->forOrganization($org)->create();

    expect($staff->can('viewAny', FosterPlacement::class))->toBeTrue()
        ->and($staff->can('view', $placement))->toBeTrue()
        ->and($staff->can('create', FosterPlacement::class))->toBeTrue()
        ->and($staff->can('update', $placement))->toBeTrue();
});

it('denies staff of another organization any per-placement action (tenant isolation)', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();
    $staffA = placementMemberOf($orgA, MembershipRole::Staff);
    $animalB = Animal::factory()->forOrganization($orgB)->create();
    $placementB = FosterPlacement::factory()->forAnimal($animalB)->forOrganization($orgB)->create();

    expect($staffA->can('view', $placementB))->toBeFalse()
        ->and($staffA->can('update', $placementB))->toBeFalse();
});

it('denies staff without organization membership any per-placement action', function () {
    $org = Organization::factory()->create();
    // Staff di un ALTRO ente: ha i permessi foster ma nessuna membership su $org.
    $otherOrg = Organization::factory()->create();
    $outsider = placementMemberOf($otherOrg, MembershipRole::Staff);
    $animal = Animal::factory()->forOrganization($org)->create();
    $placement = FosterPlacement::factory()->forAnimal($animal)->forOrganization($org)->create();

    expect($outsider->can('view', $placement))->toBeFalse()
        ->and($outsider->can('update', $placement))->toBeFalse();
});

it('lets volunteers view but not create or update placements', function () {
    $org = Organization::factory()->create();
    $volunteer = placementMemberOf($org, MembershipRole::Volunteer);
    $animal = Animal::factory()->forOrganization($org)->create();
    $placement = FosterPlacement::factory()->forAnimal($animal)->forOrganization($org)->create();

    expect($volunteer->can('view', $placement))->toBeTrue()
        ->and($volunteer->can('create', FosterPlacement::class))->toBeFalse()
        ->and($volunteer->can('update', $placement))->toBeFalse();
});

it('never allows deleting a placement', function () {
    $org = Organization::factory()->create();
    $owner = placementMemberOf($org, MembershipRole::Owner);
    $animal = Animal::factory()->forOrganization($org)->create();
    $placement = FosterPlacement::factory()->forAnimal($animal)->forOrganization($org)->create();

    expect($owner->can('delete', $placement))->toBeFalse();
});

it('lets any authenticated user viewAny placements', function () {
    $user = User::factory()->create();

    expect($user->can('viewAny', FosterPlacement::class))->toBeTrue();
});
