<?php

declare(strict_types=1);

use App\Actions\Organizations\AddOrganizationMember;
use App\Actions\Organizations\VerifyOrganizationMember;
use App\Enums\MembershipRole;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

/**
 * Imposta il contesto team e resetta la cache dei permessi.
 */
function withTeam(?int $teamId): void
{
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($teamId);
    $registrar->forgetCachedPermissions();
}

it('creates the pivot with the given role and verified flag', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();

    $pivot = app(AddOrganizationMember::class)->handle(
        organization: $organization,
        user: $user,
        role: MembershipRole::Staff,
        verified: true,
    );

    expect($pivot->role)->toBe(MembershipRole::Staff)
        ->and($pivot->verified)->toBeTrue()
        ->and($pivot->invited_at)->not->toBeNull();

    $this->assertDatabaseHas('organization_user', [
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'role' => MembershipRole::Staff->value,
        'verified' => true,
    ]);
});

it('assigns the spatie role scoped to the organization team', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();

    app(AddOrganizationMember::class)->handle(
        organization: $organization,
        user: $user,
        role: MembershipRole::Staff,
    );

    withTeam($organization->id);

    expect($user->hasRole(RoleName::OrgStaff->value))->toBeTrue();
});

it('isolates roles between organizations (teams)', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();
    $user = User::factory()->create();

    app(AddOrganizationMember::class)->handle(
        organization: $orgA,
        user: $user,
        role: MembershipRole::Staff,
    );

    withTeam($orgA->id);
    expect($user->fresh()->hasRole(RoleName::OrgStaff->value))->toBeTrue();

    withTeam($orgB->id);
    expect($user->fresh()->hasRole(RoleName::OrgStaff->value))->toBeFalse()
        ->and($user->fresh()->can(PermissionName::AnimalView->value))->toBeFalse();
});

it('verifies a previously invited member', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();

    app(AddOrganizationMember::class)->handle(
        organization: $organization,
        user: $user,
        role: MembershipRole::Staff,
        verified: false,
    );

    expect($user->isVerifiedMemberOf($organization))->toBeFalse();

    app(VerifyOrganizationMember::class)->handle($organization, $user);

    $this->assertDatabaseHas('organization_user', [
        'organization_id' => $organization->id,
        'user_id' => $user->id,
        'verified' => true,
    ]);
    expect($user->isVerifiedMemberOf($organization))->toBeTrue();
});

it('grants org staff the animal permissions but not organization update', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();

    app(AddOrganizationMember::class)->handle(
        organization: $organization,
        user: $user,
        role: MembershipRole::Staff,
        verified: true,
    );

    withTeam($organization->id);

    expect($user->can(PermissionName::AnimalView->value))->toBeTrue()
        ->and($user->can(PermissionName::AnimalCreate->value))->toBeTrue()
        ->and($user->can(PermissionName::OrganizationUpdate->value))->toBeFalse();
});

it('grants org volunteer only read permissions', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();

    app(AddOrganizationMember::class)->handle(
        organization: $organization,
        user: $user,
        role: MembershipRole::Volunteer,
        verified: true,
    );

    withTeam($organization->id);

    expect($user->can(PermissionName::AnimalView->value))->toBeTrue()
        ->and($user->can(PermissionName::MedicalRecordView->value))->toBeTrue()
        ->and($user->can(PermissionName::AnimalCreate->value))->toBeFalse()
        ->and($user->can(PermissionName::MedicalRecordCreate->value))->toBeFalse();
});

it('grants org owner every phase-1 permission', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();

    app(AddOrganizationMember::class)->handle(
        organization: $organization,
        user: $user,
        role: MembershipRole::Owner,
        verified: true,
    );

    withTeam($organization->id);

    expect($user->can(PermissionName::OrganizationUpdate->value))->toBeTrue()
        ->and($user->can(PermissionName::OrganizationManageStaff->value))->toBeTrue()
        ->and($user->can(PermissionName::AnimalDelete->value))->toBeTrue()
        ->and($user->can(PermissionName::MedicalRecordDelete->value))->toBeTrue();
});

it('is idempotent when adding the same member twice', function () {
    $organization = Organization::factory()->create();
    $user = User::factory()->create();

    app(AddOrganizationMember::class)->handle(
        organization: $organization,
        user: $user,
        role: MembershipRole::Staff,
        verified: true,
    );

    // Seconda chiamata identica: non deve lanciare (nessuna violazione del
    // vincolo unique sulla pivot) e non deve duplicare né la pivot né il ruolo.
    app(AddOrganizationMember::class)->handle(
        organization: $organization,
        user: $user,
        role: MembershipRole::Staff,
        verified: true,
    );

    // La pivot resta unica.
    expect($organization->users()->wherePivot('user_id', $user->getKey())->count())->toBe(1);
    $this->assertDatabaseCount('organization_user', 1);

    // Il ruolo spatie non è duplicato nel contesto del team.
    withTeam($organization->id);
    expect($user->roles()->where('name', RoleName::OrgStaff->value)->count())->toBe(1)
        ->and($user->hasRole(RoleName::OrgStaff->value))->toBeTrue();
});

it('lets a super admin bypass every ability via the gate', function () {
    $user = User::factory()->create();

    // In modalità teams l'assegnazione porta sempre un team id: il team è
    // irrilevante per il Super Admin (il bypass ignora il team), quindi lo
    // assegniamo in un contesto arbitrario.
    withTeam(1);
    $user->assignRole(RoleName::SuperAdmin->value);

    // Anche nel contesto di un ente diverso, il Super Admin passa i check.
    withTeam(999);

    expect($user->can(PermissionName::OrganizationUpdate->value))->toBeTrue()
        ->and($user->can(PermissionName::AnimalDelete->value))->toBeTrue();
});
