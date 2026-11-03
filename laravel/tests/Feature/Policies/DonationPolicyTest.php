<?php

declare(strict_types=1);

use App\Actions\Organizations\AddOrganizationMember;
use App\Enums\MembershipRole;
use App\Models\Donation;
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
function donationMemberOf(Organization $organization, MembershipRole $role): User
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

it('lets any authenticated user viewAny and create donations', function () {
    $user = User::factory()->create();

    expect($user->can('viewAny', Donation::class))->toBeTrue()
        ->and($user->can('create', Donation::class))->toBeTrue();
});

it('lets the donor view their own donation', function () {
    $donor = User::factory()->create();
    $donation = Donation::factory()->forDonor($donor)->create();

    expect($donor->can('view', $donation))->toBeTrue();
});

it('denies the donor update on their own donation (staff-only)', function () {
    $donor = User::factory()->create();
    $donation = Donation::factory()->forDonor($donor)->create();

    expect($donor->can('update', $donation))->toBeFalse()
        ->and($donor->can('manage', $donation))->toBeFalse();
});

it('denies a stranger view on someone else donation', function () {
    $donor = User::factory()->create();
    $stranger = User::factory()->create();
    $donation = Donation::factory()->forDonor($donor)->create();

    expect($stranger->can('view', $donation))->toBeFalse();
});

it('lets org staff view and manage donations of their own organization', function () {
    $org = Organization::factory()->create();
    $staff = donationMemberOf($org, MembershipRole::Staff);
    $donation = Donation::factory()->forOrganization($org)->anonymous()->create();

    expect($staff->can('view', $donation))->toBeTrue()
        ->and($staff->can('update', $donation))->toBeTrue()
        ->and($staff->can('manage', $donation))->toBeTrue();
});

it('lets volunteers view but not manage donations', function () {
    $org = Organization::factory()->create();
    $volunteer = donationMemberOf($org, MembershipRole::Volunteer);
    $donation = Donation::factory()->forOrganization($org)->anonymous()->create();

    expect($volunteer->can('view', $donation))->toBeTrue()
        ->and($volunteer->can('update', $donation))->toBeFalse()
        ->and($volunteer->can('manage', $donation))->toBeFalse();
});

it('denies staff of another organization any action (tenant isolation)', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();
    $staffA = donationMemberOf($orgA, MembershipRole::Staff);
    $donationB = Donation::factory()->forOrganization($orgB)->anonymous()->create();

    expect($staffA->can('view', $donationB))->toBeFalse()
        ->and($staffA->can('update', $donationB))->toBeFalse()
        ->and($staffA->can('manage', $donationB))->toBeFalse();
});

it('never allows deleting a donation', function () {
    $org = Organization::factory()->create();
    $owner = donationMemberOf($org, MembershipRole::Owner);
    $donation = Donation::factory()->forOrganization($org)->anonymous()->create();

    expect($owner->can('delete', $donation))->toBeFalse();
});
