<?php

declare(strict_types=1);

use App\Enums\MembershipRole;
use App\Filament\Shelter\Widgets\UpcomingAppointmentsWidget;
use App\Models\Animal;
use App\Models\Appointment;
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
 * Appuntamento attivo e futuro per un animale dell'ente indicato.
 */
function upcomingAppointmentFor(Organization $organization, string $state = 'confirmed'): Appointment
{
    $animal = Animal::factory()->available()->forOrganization($organization)->create();

    return Appointment::factory()
        ->{$state}()
        ->forAnimal($animal)
        ->at(now()->addDays(3)->startOfHour())
        ->create();
}

it('shows only the current tenant upcoming appointments and hides another tenant rows', function () {
    $orgA = Organization::factory()->verified()->create();
    $orgB = Organization::factory()->verified()->create();

    $apptA = upcomingAppointmentFor($orgA);
    $apptB = upcomingAppointmentFor($orgB);

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(UpcomingAppointmentsWidget::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$apptA])
        ->assertCanNotSeeTableRecords([$apptB]);
});

it('excludes past and terminal (non-active) appointments', function () {
    $org = Organization::factory()->verified()->create();

    $future = upcomingAppointmentFor($org, 'confirmed');

    // Passato → escluso (non upcoming).
    $pastAnimal = Animal::factory()->available()->forOrganization($org)->create();
    $past = Appointment::factory()->confirmed()->forAnimal($pastAnimal)->at(now()->subDays(2)->startOfHour())->create();

    // Futuro ma terminale (completato) → escluso (non active).
    $completedAnimal = Animal::factory()->available()->forOrganization($org)->create();
    $completed = Appointment::factory()->completed()->forAnimal($completedAnimal)->at(now()->addDays(2)->startOfHour())->create();

    $staff = member($org, MembershipRole::Staff);
    actAsInTenant($staff, $org);

    Livewire::test(UpcomingAppointmentsWidget::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$future])
        ->assertCanNotSeeTableRecords([$past, $completed]);
});
