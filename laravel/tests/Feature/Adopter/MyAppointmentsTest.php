<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Filament\Adopter\Pages\MyAppointments;
use App\Models\Animal;
use App\Models\Appointment;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\PermissionRegistrar;

// Specchio di tests/Feature/Adopter/MyFosterApplicationsTest.php per gli appuntamenti.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('adopter'));
    Notification::fake();
});

afterEach(function () {
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

/**
 * Un appuntamento in uno stato di factory dato, legato a un animale pubblicamente
 * visibile del suo ente e al richiedente indicato.
 */
function appointmentFor(User $requester, string $factoryState = 'requested'): Appointment
{
    $org = Organization::factory()->verified()->create();
    $animal = Animal::factory()->available()->forOrganization($org)->create();

    return Appointment::factory()
        ->{$factoryState}()
        ->forAnimal($animal)
        ->forUser($requester)
        ->create();
}

it('lets a requester reach the my-appointments page', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(MyAppointments::class)
        ->assertOk();
});

it('lists only the current users appointments, not another users', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    $mine = appointmentFor($owner);
    $theirs = appointmentFor($other);

    Livewire::actingAs($owner)
        ->test(MyAppointments::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs]);
});

it('cancels a pre-terminal appointment via the record action', function (string $factoryState) {
    $owner = User::factory()->create();
    $appointment = appointmentFor($owner, $factoryState);

    Livewire::actingAs($owner)
        ->test(MyAppointments::class)
        ->assertActionVisible(TestAction::make('cancel')->table($appointment))
        ->callAction(TestAction::make('cancel')->table($appointment))
        ->assertHasNoActionErrors();

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Cancelled)
        ->and(Activity::query()->where('description', 'appointment.cancelled')->count())->toBe(1);
})->with([
    'requested' => ['requested'],
    'confirmed' => ['confirmed'],
]);

it('hides the cancel action for a terminal appointment', function (string $factoryState) {
    $owner = User::factory()->create();
    $appointment = appointmentFor($owner, $factoryState);

    Livewire::actingAs($owner)
        ->test(MyAppointments::class)
        ->assertActionHidden(TestAction::make('cancel')->table($appointment));

    expect(Activity::query()->where('description', 'appointment.cancelled')->count())->toBe(0);
})->with([
    'completed' => ['completed'],
    'cancelled' => ['cancelled'],
    'no show' => ['noShow'],
]);

it('denies cancelling another users appointment (policy ownership)', function () {
    $owner = User::factory()->create();
    $appointment = appointmentFor($owner);

    // Il proprietario può annullare; un intruso no (policy ownership).
    expect($owner->can('cancel', $appointment))->toBeTrue();

    $intruder = User::factory()->create();
    expect($intruder->can('cancel', $appointment))->toBeFalse();
});
