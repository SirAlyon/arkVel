<?php

declare(strict_types=1);

use App\Actions\Organizations\AddOrganizationMember;
use App\Enums\MembershipRole;
use App\Models\Animal;
use App\Models\FosterApplication;
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
function fosterMemberOf(Organization $organization, MembershipRole $role): User
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

it('lets the applicant view and withdraw their own foster application', function () {
    $applicant = User::factory()->create();
    $fosterApplication = FosterApplication::factory()->forAdopter($applicant)->create();

    expect($applicant->can('view', $fosterApplication))->toBeTrue()
        ->and($applicant->can('withdraw', $fosterApplication))->toBeTrue();
});

it('denies the applicant view/withdraw on someone else foster application', function () {
    $applicant = User::factory()->create();
    $other = User::factory()->create();
    $fosterApplication = FosterApplication::factory()->forAdopter($other)->create();

    expect($applicant->can('view', $fosterApplication))->toBeFalse()
        ->and($applicant->can('withdraw', $fosterApplication))->toBeFalse();
});

it('lets org staff view and update foster applications of their own organization', function () {
    $org = Organization::factory()->create();
    $staff = fosterMemberOf($org, MembershipRole::Staff);
    $animal = Animal::factory()->forOrganization($org)->create();
    $fosterApplication = FosterApplication::factory()->forAnimal($animal)->forOrganization($org)->create();

    expect($staff->can('viewAny', FosterApplication::class))->toBeTrue()
        ->and($staff->can('view', $fosterApplication))->toBeTrue()
        ->and($staff->can('update', $fosterApplication))->toBeTrue();
});

it('denies staff of another organization any per-foster-application action (tenant isolation)', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();
    $staffA = fosterMemberOf($orgA, MembershipRole::Staff);
    $animalB = Animal::factory()->forOrganization($orgB)->create();
    $fosterApplicationB = FosterApplication::factory()->forAnimal($animalB)->forOrganization($orgB)->create();

    expect($staffA->can('view', $fosterApplicationB))->toBeFalse()
        ->and($staffA->can('update', $fosterApplicationB))->toBeFalse();
});

it('denies staff without organization membership any per-foster-application action', function () {
    $org = Organization::factory()->create();
    // Staff di un ALTRO ente: ha i permessi foster ma nessuna membership su $org.
    $otherOrg = Organization::factory()->create();
    $outsider = fosterMemberOf($otherOrg, MembershipRole::Staff);
    $animal = Animal::factory()->forOrganization($org)->create();
    $fosterApplication = FosterApplication::factory()->forAnimal($animal)->forOrganization($org)->create();

    expect($outsider->can('view', $fosterApplication))->toBeFalse()
        ->and($outsider->can('update', $fosterApplication))->toBeFalse();
});

it('lets volunteers view but not update foster applications', function () {
    $org = Organization::factory()->create();
    $volunteer = fosterMemberOf($org, MembershipRole::Volunteer);
    $animal = Animal::factory()->forOrganization($org)->create();
    $fosterApplication = FosterApplication::factory()->forAnimal($animal)->forOrganization($org)->create();

    expect($volunteer->can('view', $fosterApplication))->toBeTrue()
        ->and($volunteer->can('update', $fosterApplication))->toBeFalse();
});

it('never allows deleting a foster application', function () {
    $org = Organization::factory()->create();
    $owner = fosterMemberOf($org, MembershipRole::Owner);
    $animal = Animal::factory()->forOrganization($org)->create();
    $fosterApplication = FosterApplication::factory()->forAnimal($animal)->forOrganization($org)->create();

    expect($owner->can('delete', $fosterApplication))->toBeFalse();
});

it('lets any authenticated user viewAny and create foster applications', function () {
    $user = User::factory()->create();

    expect($user->can('viewAny', FosterApplication::class))->toBeTrue()
        ->and($user->can('create', FosterApplication::class))->toBeTrue();
});
