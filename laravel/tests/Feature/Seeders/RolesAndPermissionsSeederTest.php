<?php

declare(strict_types=1);

use App\Enums\PermissionName;
use App\Enums\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('creates every phase-1 role and permission', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Role::count())->toBe(count(RoleName::cases()))
        ->and(Permission::count())->toBe(count(PermissionName::cases()));

    foreach (RoleName::cases() as $role) {
        expect(Role::where('name', $role->value)->exists())->toBeTrue();
    }

    foreach (PermissionName::cases() as $permission) {
        expect(Permission::where('name', $permission->value)->exists())->toBeTrue();
    }
});

it('creates roles as global definitions (no team id)', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Role::whereNotNull('team_id')->count())->toBe(0);
});

it('maps role permissions correctly', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $owner = Role::where('name', RoleName::OrgOwner->value)->firstOrFail();
    $staff = Role::where('name', RoleName::OrgStaff->value)->firstOrFail();
    $volunteer = Role::where('name', RoleName::OrgVolunteer->value)->firstOrFail();
    $adopter = Role::where('name', RoleName::Adopter->value)->firstOrFail();
    $superAdmin = Role::where('name', RoleName::SuperAdmin->value)->firstOrFail();

    expect($owner->permissions)->toHaveCount(count(PermissionName::cases()))
        ->and($staff->permissions)->toHaveCount(15)
        ->and($staff->permissions->pluck('name'))
        ->not->toContain(PermissionName::OrganizationUpdate->value)
        ->and($staff->permissions->pluck('name'))
        ->toContain(
            PermissionName::ApplicationView->value,
            PermissionName::ApplicationManage->value,
            PermissionName::FosterApplicationView->value,
            PermissionName::FosterApplicationManage->value,
            PermissionName::AppointmentView->value,
            PermissionName::AppointmentManage->value,
            PermissionName::CalendarManage->value,
        )
        ->and($volunteer->permissions->pluck('name'))
        ->not->toContain(PermissionName::CalendarManage->value)
        ->and($volunteer->permissions->pluck('name')->sort()->values()->all())
        ->toBe([
            PermissionName::AnimalView->value,
            PermissionName::ApplicationView->value,
            PermissionName::AppointmentView->value,
            PermissionName::FosterApplicationView->value,
            PermissionName::MedicalRecordView->value,
        ])
        ->and($adopter->permissions)->toHaveCount(0)
        ->and($superAdmin->permissions)->toHaveCount(0);
});

it('is idempotent when run twice', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Role::count())->toBe(count(RoleName::cases()))
        ->and(Permission::count())->toBe(count(PermissionName::cases()));

    $owner = Role::where('name', RoleName::OrgOwner->value)->firstOrFail();
    expect($owner->permissions)->toHaveCount(count(PermissionName::cases()));
});
