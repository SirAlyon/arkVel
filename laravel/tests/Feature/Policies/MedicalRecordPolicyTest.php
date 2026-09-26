<?php

declare(strict_types=1);

use App\Actions\Organizations\AddOrganizationMember;
use App\Enums\MembershipRole;
use App\Enums\RoleName;
use App\Models\Animal;
use App\Models\MedicalRecord;
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
 * Crea un utente membro verificato dell'ente col ruolo indicato, poi imposta il
 * team id sul registrar così che i check `can()` valutino i permessi
 * team-scoped di quell'ente.
 */
function medicalMemberOf(Organization $organization, MembershipRole $role): User
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

it('lets org staff view, create, update and delete records of animals in their own organization', function () {
    $orgA = Organization::factory()->create();
    $staff = medicalMemberOf($orgA, MembershipRole::Staff);
    $animalA = Animal::factory()->forOrganization($orgA)->create();
    $record = MedicalRecord::factory()->forAnimal($animalA)->create();

    expect($staff->can('viewAny', MedicalRecord::class))->toBeTrue()
        ->and($staff->can('view', $record))->toBeTrue()
        ->and($staff->can('create', MedicalRecord::class))->toBeTrue()
        ->and($staff->can('update', $record))->toBeTrue()
        ->and($staff->can('delete', $record))->toBeTrue();
});

it('denies org staff any per-record action on another organization (tenant isolation via animal)', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();
    $staff = medicalMemberOf($orgA, MembershipRole::Staff);

    $animalB = Animal::factory()->forOrganization($orgB)->create();
    $recordB = MedicalRecord::factory()->forAnimal($animalB)->create();

    expect($staff->can('view', $recordB))->toBeFalse()
        ->and($staff->can('update', $recordB))->toBeFalse()
        ->and($staff->can('delete', $recordB))->toBeFalse();
});

it('lets org volunteers only view records', function () {
    $orgA = Organization::factory()->create();
    $volunteer = medicalMemberOf($orgA, MembershipRole::Volunteer);
    $animalA = Animal::factory()->forOrganization($orgA)->create();
    $record = MedicalRecord::factory()->forAnimal($animalA)->create();

    expect($volunteer->can('viewAny', MedicalRecord::class))->toBeTrue()
        ->and($volunteer->can('view', $record))->toBeTrue()
        ->and($volunteer->can('create', MedicalRecord::class))->toBeFalse()
        ->and($volunteer->can('update', $record))->toBeFalse()
        ->and($volunteer->can('delete', $record))->toBeFalse();
});

it('lets a super admin act on records of any organization via the gate', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();
    $superAdmin = User::factory()->create();

    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($orgA->getKey());
    $superAdmin->assignRole(RoleName::SuperAdmin->value);
    $registrar->forgetCachedPermissions();

    $animalB = Animal::factory()->forOrganization($orgB)->create();
    $recordB = MedicalRecord::factory()->forAnimal($animalB)->create();

    expect($superAdmin->can('viewAny', MedicalRecord::class))->toBeTrue()
        ->and($superAdmin->can('view', $recordB))->toBeTrue()
        ->and($superAdmin->can('create', MedicalRecord::class))->toBeTrue()
        ->and($superAdmin->can('update', $recordB))->toBeTrue()
        ->and($superAdmin->can('delete', $recordB))->toBeTrue();
});
