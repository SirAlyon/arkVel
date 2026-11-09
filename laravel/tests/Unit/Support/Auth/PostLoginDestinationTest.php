<?php

declare(strict_types=1);

use App\Enums\MembershipRole;
use App\Enums\OrganizationStatus;
use App\Enums\RoleName;
use App\Models\Organization;
use App\Models\User;
use App\Support\Auth\PostLoginDestination;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('sends adopters and guests to the adopter area', function (): void {
    $destination = app(PostLoginDestination::class);

    expect($destination->for(User::factory()->create()))->toBe(route('filament.adopter.home'))
        ->and($destination->for(null))->toBe(route('filament.adopter.home'));
});

it('sends verified members of an operable organization to /manage', function (): void {
    $member = member(Organization::factory()->verified()->create(), MembershipRole::Volunteer);

    expect(app(PostLoginDestination::class)->for($member))->toBe(route('filament.shelter.tenant'));
});

it('does not send members of a suspended organization to /manage', function (): void {
    $member = member(Organization::factory()->create(['status' => OrganizationStatus::Suspended]), MembershipRole::Volunteer);

    expect(app(PostLoginDestination::class)->for($member))->toBe(route('filament.adopter.home'));
});

it('sends the Super Admin to /admin', function (): void {
    $admin = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId(RoleName::PLATFORM_TEAM_ID);
    $admin->assignRole(RoleName::SuperAdmin->value);

    expect(app(PostLoginDestination::class)->for($admin))->toBe(route('filament.admin.pages.dashboard'));
});
