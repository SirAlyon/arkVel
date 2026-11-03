<?php

declare(strict_types=1);

use App\Actions\Organizations\AddOrganizationMember;
use App\Enums\MembershipRole;
use App\Models\MessageThread;
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
function messagingMemberOf(Organization $organization, MembershipRole $role): User
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

it('lets any authenticated user viewAny and create threads', function () {
    $user = User::factory()->create();

    expect($user->can('viewAny', MessageThread::class))->toBeTrue()
        ->and($user->can('create', MessageThread::class))->toBeTrue();
});

it('lets the adopter participant view and reply to their own thread', function () {
    $adopter = User::factory()->create();
    $thread = MessageThread::factory()->forAdopter($adopter)->create();

    expect($adopter->can('view', $thread))->toBeTrue()
        ->and($adopter->can('reply', $thread))->toBeTrue();
});

it('denies the adopter view/reply on someone else thread', function () {
    $adopter = User::factory()->create();
    $other = User::factory()->create();
    $thread = MessageThread::factory()->forAdopter($other)->create();

    expect($adopter->can('view', $thread))->toBeFalse()
        ->and($adopter->can('reply', $thread))->toBeFalse();
});

it('lets org staff view and reply to threads of their own organization', function () {
    $org = Organization::factory()->create();
    $staff = messagingMemberOf($org, MembershipRole::Staff);
    $thread = MessageThread::factory()->forOrganization($org)->create();

    expect($staff->can('viewAny', MessageThread::class))->toBeTrue()
        ->and($staff->can('view', $thread))->toBeTrue()
        ->and($staff->can('reply', $thread))->toBeTrue();
});

it('denies staff of another organization any per-thread action (tenant isolation)', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();
    $staffA = messagingMemberOf($orgA, MembershipRole::Staff);
    $threadB = MessageThread::factory()->forOrganization($orgB)->create();

    expect($staffA->can('view', $threadB))->toBeFalse()
        ->and($staffA->can('reply', $threadB))->toBeFalse();
});

it('denies staff without membership on the thread organization', function () {
    $org = Organization::factory()->create();
    // Staff di un ALTRO ente: ha i permessi ma nessuna membership su $org.
    $otherOrg = Organization::factory()->create();
    $outsider = messagingMemberOf($otherOrg, MembershipRole::Staff);
    $thread = MessageThread::factory()->forOrganization($org)->create();

    expect($outsider->can('view', $thread))->toBeFalse()
        ->and($outsider->can('reply', $thread))->toBeFalse();
});

it('lets volunteers view but not reply to threads', function () {
    $org = Organization::factory()->create();
    $volunteer = messagingMemberOf($org, MembershipRole::Volunteer);
    $thread = MessageThread::factory()->forOrganization($org)->create();

    expect($volunteer->can('view', $thread))->toBeTrue()
        ->and($volunteer->can('reply', $thread))->toBeFalse();
});

it('never allows deleting a thread', function () {
    $org = Organization::factory()->create();
    $owner = messagingMemberOf($org, MembershipRole::Owner);
    $thread = MessageThread::factory()->forOrganization($org)->create();

    expect($owner->can('delete', $thread))->toBeFalse();
});
