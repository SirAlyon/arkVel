<?php

declare(strict_types=1);

use App\Enums\MembershipRole;
use App\Enums\RoleName;
use App\Filament\Resources\Organizations\OrganizationResource;
use App\Filament\Resources\Organizations\Pages\EditOrganization;
use App\Filament\Resources\Organizations\Pages\ListOrganizations;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

// Flag «Ente dimostrativo»: solo il Super Admin lo cambia, dal pannello /admin. Lo staff
// degli enti (anche l'Owner, che ha organization.update) non vede la Resource.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

afterEach(function () {
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

function demoToggleSuperAdmin(): User
{
    $user = User::factory()->create();

    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId(0);
    $user->assignRole(RoleName::SuperAdmin->value);
    $registrar->forgetCachedPermissions();

    return $user;
}

it('lets the super admin list organizations and turn the demo flag on and off', function () {
    $admin = demoToggleSuperAdmin();
    $organization = Organization::factory()->verified()->create(['name' => 'Kiba']);

    Livewire::actingAs($admin)
        ->test(ListOrganizations::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$organization]);

    Livewire::actingAs($admin)
        ->test(EditOrganization::class, ['record' => $organization->getRouteKey()])
        ->assertOk()
        ->assertSee(__('demo.admin.toggle'))
        ->fillForm(['is_demo' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($organization->fresh()->is_demo)->toBeTrue();

    Livewire::actingAs($admin)
        ->test(EditOrganization::class, ['record' => $organization->getRouteKey()])
        ->fillForm(['is_demo' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($organization->fresh()->is_demo)->toBeFalse();
});

it('only changes the demo flag from the admin form', function () {
    $organization = Organization::factory()->verified()->create(['name' => 'Kiba']);

    Livewire::actingAs(demoToggleSuperAdmin())
        ->test(EditOrganization::class, ['record' => $organization->getRouteKey()])
        ->fillForm(['is_demo' => true])
        ->call('save');

    $fresh = $organization->fresh();
    expect($fresh->name)->toBe('Kiba')
        ->and($fresh->status)->toBe($organization->status);
});

it('denies the resource to a plain user', function () {
    $organization = Organization::factory()->verified()->create();
    $this->actingAs(User::factory()->create());

    expect(OrganizationResource::canViewAny())->toBeFalse()
        ->and(OrganizationResource::canEdit($organization))->toBeFalse();

    Livewire::test(EditOrganization::class, ['record' => $organization->getRouteKey()])
        ->assertForbidden();
});

it('denies the resource to the owner of the organization', function () {
    $organization = Organization::factory()->verified()->create();
    $owner = member($organization, MembershipRole::Owner);

    Livewire::actingAs($owner)
        ->test(EditOrganization::class, ['record' => $organization->getRouteKey()])
        ->assertForbidden();

    Livewire::actingAs($owner)
        ->test(ListOrganizations::class)
        ->assertForbidden();

    expect($organization->fresh()->is_demo)->toBeFalse();
});

it('is not part of the organization staff panel', function () {
    expect(Filament::getPanel('shelter')->getResources())->not->toContain(OrganizationResource::class)
        ->and(Filament::getPanel('admin')->getResources())->toContain(OrganizationResource::class);
});

it('never offers create or delete', function () {
    $this->actingAs(demoToggleSuperAdmin());

    expect(OrganizationResource::canCreate())->toBeFalse()
        ->and(OrganizationResource::canDelete(Organization::factory()->create()))->toBeFalse()
        ->and(OrganizationResource::canDeleteAny())->toBeFalse();
});
