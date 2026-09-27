<?php

declare(strict_types=1);

use App\Actions\Organizations\AddOrganizationMember;
use App\Enums\MembershipRole;
use App\Models\Animal;
use App\Models\Appointment;
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
function appointmentMemberOf(Organization $organization, MembershipRole $role): User
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

it('lets any authenticated user viewAny and create appointments', function () {
    $user = User::factory()->create();

    expect($user->can('viewAny', Appointment::class))->toBeTrue()
        ->and($user->can('create', Appointment::class))->toBeTrue();
});

it('lets the requester view and cancel their own appointment', function () {
    $requester = User::factory()->create();
    $appointment = Appointment::factory()->forUser($requester)->create();

    expect($requester->can('view', $appointment))->toBeTrue()
        ->and($requester->can('cancel', $appointment))->toBeTrue();
});

it('denies the requester update on their own appointment (staff-only)', function () {
    $requester = User::factory()->create();
    $appointment = Appointment::factory()->forUser($requester)->create();

    expect($requester->can('update', $appointment))->toBeFalse();
});

it('denies a stranger view/cancel on someone else appointment', function () {
    $requester = User::factory()->create();
    $stranger = User::factory()->create();
    $appointment = Appointment::factory()->forUser($requester)->create();

    expect($stranger->can('view', $appointment))->toBeFalse()
        ->and($stranger->can('cancel', $appointment))->toBeFalse();
});

it('lets org staff view, update and cancel appointments of their own organization', function () {
    $org = Organization::factory()->create();
    $staff = appointmentMemberOf($org, MembershipRole::Staff);
    $animal = Animal::factory()->forOrganization($org)->create();
    $appointment = Appointment::factory()->forAnimal($animal)->forOrganization($org)->create();

    expect($staff->can('view', $appointment))->toBeTrue()
        ->and($staff->can('update', $appointment))->toBeTrue()
        ->and($staff->can('cancel', $appointment))->toBeTrue();
});

it('lets volunteers view but not update or cancel appointments', function () {
    $org = Organization::factory()->create();
    $volunteer = appointmentMemberOf($org, MembershipRole::Volunteer);
    $animal = Animal::factory()->forOrganization($org)->create();
    $appointment = Appointment::factory()->forAnimal($animal)->forOrganization($org)->create();

    expect($volunteer->can('view', $appointment))->toBeTrue()
        ->and($volunteer->can('update', $appointment))->toBeFalse()
        ->and($volunteer->can('cancel', $appointment))->toBeFalse();
});

it('denies staff of another organization any per-appointment action (tenant isolation)', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();
    $staffA = appointmentMemberOf($orgA, MembershipRole::Staff);
    $animalB = Animal::factory()->forOrganization($orgB)->create();
    $appointmentB = Appointment::factory()->forAnimal($animalB)->forOrganization($orgB)->create();

    expect($staffA->can('view', $appointmentB))->toBeFalse()
        ->and($staffA->can('update', $appointmentB))->toBeFalse()
        ->and($staffA->can('cancel', $appointmentB))->toBeFalse();
});

it('never allows deleting an appointment', function () {
    $org = Organization::factory()->create();
    $owner = appointmentMemberOf($org, MembershipRole::Owner);
    $animal = Animal::factory()->forOrganization($org)->create();
    $appointment = Appointment::factory()->forAnimal($animal)->forOrganization($org)->create();

    expect($owner->can('delete', $appointment))->toBeFalse();
});
