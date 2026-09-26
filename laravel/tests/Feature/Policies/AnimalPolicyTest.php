<?php

declare(strict_types=1);

use App\Actions\Organizations\AddOrganizationMember;
use App\Enums\MembershipRole;
use App\Enums\RoleName;
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
    // Ripristina il contesto team per non contaminare i test successivi.
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

/**
 * Crea un utente membro verificato dell'ente con il ruolo indicato, poi imposta
 * il team id sul registrar cosi che i check `can()` valutino i permessi
 * team-scoped di quell'ente.
 */
function memberOf(Organization $organization, MembershipRole $role): User
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

it('lets org staff view, create, update and delete animals of their own organization', function () {
    $orgA = Organization::factory()->create();
    $staff = memberOf($orgA, MembershipRole::Staff);
    $animal = Animal::factory()->forOrganization($orgA)->create();

    expect($staff->can('viewAny', Animal::class))->toBeTrue()
        ->and($staff->can('view', $animal))->toBeTrue()
        ->and($staff->can('create', Animal::class))->toBeTrue()
        ->and($staff->can('update', $animal))->toBeTrue()
        ->and($staff->can('delete', $animal))->toBeTrue()
        ->and($staff->can('restore', $animal))->toBeTrue()
        ->and($staff->can('forceDelete', $animal))->toBeTrue();
});

it('denies org staff any per-animal action on another organization (tenant isolation)', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();
    $staff = memberOf($orgA, MembershipRole::Staff);
    $animalB = Animal::factory()->forOrganization($orgB)->create();

    expect($staff->can('view', $animalB))->toBeFalse()
        ->and($staff->can('update', $animalB))->toBeFalse()
        ->and($staff->can('delete', $animalB))->toBeFalse()
        ->and($staff->can('restore', $animalB))->toBeFalse()
        ->and($staff->can('forceDelete', $animalB))->toBeFalse();
});

it('lets org volunteers only view animals', function () {
    $orgA = Organization::factory()->create();
    $volunteer = memberOf($orgA, MembershipRole::Volunteer);
    $animal = Animal::factory()->forOrganization($orgA)->create();

    expect($volunteer->can('viewAny', Animal::class))->toBeTrue()
        ->and($volunteer->can('view', $animal))->toBeTrue()
        ->and($volunteer->can('create', Animal::class))->toBeFalse()
        ->and($volunteer->can('update', $animal))->toBeFalse()
        ->and($volunteer->can('delete', $animal))->toBeFalse()
        ->and($volunteer->can('restore', $animal))->toBeFalse()
        ->and($volunteer->can('forceDelete', $animal))->toBeFalse();
});

it('lets a super admin act on any organization via the gate', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();
    $superAdmin = User::factory()->create();

    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($orgA->getKey());
    $superAdmin->assignRole(RoleName::SuperAdmin->value);
    $registrar->forgetCachedPermissions();

    $animalB = Animal::factory()->forOrganization($orgB)->create();

    expect($superAdmin->can('viewAny', Animal::class))->toBeTrue()
        ->and($superAdmin->can('view', $animalB))->toBeTrue()
        ->and($superAdmin->can('create', Animal::class))->toBeTrue()
        ->and($superAdmin->can('update', $animalB))->toBeTrue()
        ->and($superAdmin->can('delete', $animalB))->toBeTrue();
});
