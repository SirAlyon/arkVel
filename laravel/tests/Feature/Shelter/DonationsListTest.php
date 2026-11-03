<?php

declare(strict_types=1);

use App\Enums\MembershipRole;
use App\Filament\Shelter\Resources\Donations\DonationResource;
use App\Filament\Shelter\Resources\Donations\Pages\ListDonations;
use App\Models\Donation;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

// Registro donazioni del pannello shelter (P4-6 STEP 5): tenant-scoped, sola lettura.
// Gli helper member() e actAsInTenant() sono in tests/Pest.php.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

afterEach(function () {
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

it('renders the donations list for a verified staff member of the tenant', function () {
    $org = Organization::factory()->verified()->create();
    $staff = member($org, MembershipRole::Staff);
    actAsInTenant($staff, $org);

    Livewire::test(ListDonations::class)->assertOk();
});

it('lists only the current tenants donations (cross-tenant isolation)', function () {
    $orgA = Organization::factory()->verified()->create();
    $orgB = Organization::factory()->verified()->create();

    $mine = Donation::factory()->forOrganization($orgA)->completed()->create(['donor_name' => 'Donatore A']);
    $theirs = Donation::factory()->forOrganization($orgB)->completed()->create(['donor_name' => 'Donatore B']);

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(ListDonations::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs]);
});

it('lets a volunteer view the donations list', function () {
    $org = Organization::factory()->verified()->create();
    $donation = Donation::factory()->forOrganization($org)->completed()->create();

    $volunteer = member($org, MembershipRole::Volunteer);
    actAsInTenant($volunteer, $org);

    expect(DonationResource::canAccess())->toBeTrue();

    Livewire::test(ListDonations::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$donation]);
});

it('denies access to a non-member of the tenant', function () {
    $org = Organization::factory()->verified()->create();
    $stranger = User::factory()->create();
    actAsInTenant($stranger, $org);

    expect(DonationResource::canAccess())->toBeFalse();
});

it('shows Anonimo for a donation without a donor name', function () {
    $org = Organization::factory()->verified()->create();
    $donation = Donation::factory()->forOrganization($org)->anonymous()->completed()->create();

    $staff = member($org, MembershipRole::Staff);
    actAsInTenant($staff, $org);

    Livewire::test(ListDonations::class)
        ->assertOk()
        ->assertTableColumnFormattedStateSet('donor_name', __('donations.shelter.anonymous'), record: $donation);
});

it('does not allow creating donations from the panel', function () {
    expect(DonationResource::canCreate())->toBeFalse();
});
