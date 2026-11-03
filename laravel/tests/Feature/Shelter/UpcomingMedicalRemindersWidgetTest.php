<?php

declare(strict_types=1);

use App\Enums\MembershipRole;
use App\Filament\Shelter\Widgets\UpcomingMedicalRemindersWidget;
use App\Models\Animal;
use App\Models\MedicalRecord;
use App\Models\Organization;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

// Helper member()/actAsInTenant() definiti in tests/Pest.php.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

afterEach(function () {
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

/**
 * Voce sanitaria in scadenza (fra 10 giorni) per un animale dell'ente indicato.
 */
function upcomingMedicalRecordFor(Organization $organization): MedicalRecord
{
    $animal = Animal::factory()->available()->forOrganization($organization)->create();

    return MedicalRecord::factory()->forAnimal($animal)->create([
        'next_due_at' => now()->addDays(10)->toDateString(),
    ]);
}

it('shows only the current tenant medical due dates and hides another tenant rows', function () {
    $orgA = Organization::factory()->verified()->create();
    $orgB = Organization::factory()->verified()->create();

    $recordA = upcomingMedicalRecordFor($orgA);
    $recordB = upcomingMedicalRecordFor($orgB);

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(UpcomingMedicalRemindersWidget::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$recordA])
        ->assertCanNotSeeTableRecords([$recordB]);
});

it('excludes records due beyond the 30-day window', function () {
    $org = Organization::factory()->verified()->create();

    $inWindow = upcomingMedicalRecordFor($org);

    $animalFar = Animal::factory()->available()->forOrganization($org)->create();
    $farOut = MedicalRecord::factory()->forAnimal($animalFar)->create([
        'next_due_at' => now()->addDays(60)->toDateString(),
    ]);

    $staff = member($org, MembershipRole::Staff);
    actAsInTenant($staff, $org);

    Livewire::test(UpcomingMedicalRemindersWidget::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$inWindow])
        ->assertCanNotSeeTableRecords([$farOut]);
});
