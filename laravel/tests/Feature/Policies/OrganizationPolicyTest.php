<?php

declare(strict_types=1);

use App\Actions\Organizations\AddOrganizationMember;
use App\Enums\MembershipRole;
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

it('allows any authenticated user to view the list', function () {
    $user = User::factory()->create();

    expect($user->can('viewAny', Organization::class))->toBeTrue();
});

it('allows any authenticated user to view a single organization', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    expect($user->can('view', $organization))->toBeTrue();
});

it('allows any authenticated user to register a new organization (self-service)', function () {
    // Decisione di prodotto: chi registra un ente lo gestisce subito internamente.
    // La verifica Super Admin gaterà la visibilità PUBBLICA in Fase 2, non questa
    // creazione; l'ente nasce comunque in stato Pending.
    $user = User::factory()->create();

    expect($user->can('create', Organization::class))->toBeTrue();
});

it('denies deletion by default', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    expect($user->can('delete', $organization))->toBeFalse();
});

it('denies update for a user without the organization.update permission', function () {
    $user = User::factory()->create();
    $organization = Organization::factory()->create();

    app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);

    expect($user->can('update', $organization))->toBeFalse();
});

it('allows update for an org owner in the organization team context', function () {
    $organization = Organization::factory()->create();
    $owner = User::factory()->create();

    app(AddOrganizationMember::class)->handle(
        organization: $organization,
        user: $owner,
        role: MembershipRole::Owner,
        verified: true,
    );

    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($organization->id);
    $registrar->forgetCachedPermissions();

    expect($owner->can('update', $organization))->toBeTrue();
});

it('denies update for an org staff member', function () {
    $organization = Organization::factory()->create();
    $staff = User::factory()->create();

    app(AddOrganizationMember::class)->handle(
        organization: $organization,
        user: $staff,
        role: MembershipRole::Staff,
        verified: true,
    );

    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($organization->id);
    $registrar->forgetCachedPermissions();

    expect($staff->can('update', $organization))->toBeFalse();
});

it('allows a super admin to update any organization via the gate', function () {
    $organization = Organization::factory()->create();
    $superAdmin = User::factory()->create();

    // Il team è irrilevante per il Super Admin (il bypass ignora il team);
    // in modalità teams l'assegnazione richiede comunque un team id.
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($organization->id);
    $superAdmin->assignRole(RoleName::SuperAdmin->value);
    $registrar->forgetCachedPermissions();

    expect($superAdmin->can('update', $organization))->toBeTrue();
});
