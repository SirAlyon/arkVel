<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Enums\MembershipRole;
use App\Filament\Shelter\Resources\Appointments\Pages\ListAppointments;
use App\Filament\Shelter\Resources\Appointments\Pages\ViewAppointment;
use App\Models\Animal;
use App\Models\Appointment;
use App\Models\Organization;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\PermissionRegistrar;

// I helper member() e actAsInTenant() sono definiti in tests/Pest.php (condivisi da
// tutti i test dello shelter). Specchio di FosterApplicationResourceTest.php.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Notification::fake();
});

afterEach(function () {
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

/**
 * Un appuntamento in uno stato dato per un animale dell'ente indicato.
 */
function shelterAppointmentFor(Organization $organization, string $factoryState = 'requested'): Appointment
{
    $animal = Animal::factory()->available()->forOrganization($organization)->create();

    return Appointment::factory()
        ->{$factoryState}()
        ->forAnimal($animal)
        ->create();
}

it('scopes the appointment list to the current tenant and hides other tenants appointments (cross-tenant isolation)', function () {
    $orgA = Organization::factory()->verified()->create();
    $orgB = Organization::factory()->verified()->create();

    $aAppointments = collect(range(1, 3))->map(fn (): Appointment => shelterAppointmentFor($orgA));
    $bAppointments = collect(range(1, 2))->map(fn (): Appointment => shelterAppointmentFor($orgB));

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(ListAppointments::class)
        ->assertOk()
        ->assertCanSeeTableRecords($aAppointments)
        ->assertCanNotSeeTableRecords($bAppointments);
});

it('confirms a requested appointment via the table action and persists it', function () {
    $orgA = Organization::factory()->verified()->create();
    $appointment = shelterAppointmentFor($orgA, 'requested');

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(ListAppointments::class)
        ->assertActionVisible(TestAction::make('confirm')->table($appointment))
        ->callAction(TestAction::make('confirm')->table($appointment))
        ->assertHasNoActionErrors();

    expect($appointment->refresh()->status)->toBe(AppointmentStatus::Confirmed)
        ->and(Activity::query()->where('description', 'appointment.confirmed')->count())->toBe(1);
})->group('transitions');

it('completes a confirmed appointment via the table action', function () {
    $orgA = Organization::factory()->verified()->create();
    $appointment = shelterAppointmentFor($orgA, 'confirmed');

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(ListAppointments::class)
        ->assertActionVisible(TestAction::make('complete')->table($appointment))
        ->callAction(TestAction::make('complete')->table($appointment))
        ->assertHasNoActionErrors();

    expect($appointment->refresh()->status)->toBe(AppointmentStatus::Completed)
        ->and(Activity::query()->where('description', 'appointment.completed')->count())->toBe(1);
})->group('transitions');

it('marks a confirmed appointment as no-show via the table action', function () {
    $orgA = Organization::factory()->verified()->create();
    $appointment = shelterAppointmentFor($orgA, 'confirmed');

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(ListAppointments::class)
        ->assertActionVisible(TestAction::make('noShow')->table($appointment))
        ->callAction(TestAction::make('noShow')->table($appointment))
        ->assertHasNoActionErrors();

    expect($appointment->refresh()->status)->toBe(AppointmentStatus::NoShow)
        ->and(Activity::query()->where('description', 'appointment.no_show')->count())->toBe(1);
})->group('transitions');

it('cancels an appointment via the table action with a reason', function () {
    $orgA = Organization::factory()->verified()->create();
    $appointment = shelterAppointmentFor($orgA, 'requested');

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(ListAppointments::class)
        ->assertActionVisible(TestAction::make('cancel')->table($appointment))
        ->callAction(
            TestAction::make('cancel')->table($appointment),
            ['reason' => 'L\'ente è chiuso quel giorno.'],
        )
        ->assertHasNoActionErrors();

    expect($appointment->refresh()->status)->toBe(AppointmentStatus::Cancelled)
        ->and(Activity::query()->where('description', 'appointment.cancelled')->count())->toBe(1);
})->group('transitions');

it('hides the confirm action once an appointment is confirmed', function () {
    $orgA = Organization::factory()->verified()->create();
    $appointment = shelterAppointmentFor($orgA, 'confirmed');

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(ListAppointments::class)
        ->assertActionHidden(TestAction::make('confirm')->table($appointment));
});

it('hides complete/no-show/cancel actions on a terminal appointment', function () {
    $orgA = Organization::factory()->verified()->create();
    $appointment = shelterAppointmentFor($orgA, 'completed');

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(ListAppointments::class)
        ->assertActionHidden(TestAction::make('confirm')->table($appointment))
        ->assertActionHidden(TestAction::make('complete')->table($appointment))
        ->assertActionHidden(TestAction::make('noShow')->table($appointment))
        ->assertActionHidden(TestAction::make('cancel')->table($appointment));
});

it('filters appointments by status', function () {
    $orgA = Organization::factory()->verified()->create();
    $requested = shelterAppointmentFor($orgA, 'requested');
    $confirmed = shelterAppointmentFor($orgA, 'confirmed');

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(ListAppointments::class)
        ->filterTable('status', AppointmentStatus::Confirmed->value)
        ->assertCanSeeTableRecords([$confirmed])
        ->assertCanNotSeeTableRecords([$requested]);
});

it('filters appointments by type', function () {
    $orgA = Organization::factory()->verified()->create();

    $animalVisit = Animal::factory()->available()->forOrganization($orgA)->create();
    $visit = Appointment::factory()->requested()->ofType(AppointmentType::Visit)->forAnimal($animalVisit)->create();

    $animalInterview = Animal::factory()->available()->forOrganization($orgA)->create();
    $interview = Appointment::factory()->requested()->ofType(AppointmentType::Interview)->forAnimal($animalInterview)->create();

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(ListAppointments::class)
        ->filterTable('type', AppointmentType::Interview->value)
        ->assertCanSeeTableRecords([$interview])
        ->assertCanNotSeeTableRecords([$visit]);
});

it('filters appointments by date range on scheduled_at', function () {
    $orgA = Organization::factory()->verified()->create();

    $soonAnimal = Animal::factory()->available()->forOrganization($orgA)->create();
    $soon = Appointment::factory()->requested()->forAnimal($soonAnimal)->at(now()->addDays(2)->startOfHour())->create();

    $laterAnimal = Animal::factory()->available()->forOrganization($orgA)->create();
    $later = Appointment::factory()->requested()->forAnimal($laterAnimal)->at(now()->addDays(20)->startOfHour())->create();

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    // Finestra che include solo l'appuntamento imminente.
    Livewire::test(ListAppointments::class)
        ->filterTable('scheduled_at', [
            'from' => now()->addDay()->toDateString(),
            'to' => now()->addDays(5)->toDateString(),
        ])
        ->assertCanSeeTableRecords([$soon])
        ->assertCanNotSeeTableRecords([$later]);
});

it('filters to upcoming appointments only', function () {
    $orgA = Organization::factory()->verified()->create();

    $futureAnimal = Animal::factory()->available()->forOrganization($orgA)->create();
    $future = Appointment::factory()->confirmed()->forAnimal($futureAnimal)->at(now()->addDays(3)->startOfHour())->create();

    $pastAnimal = Animal::factory()->available()->forOrganization($orgA)->create();
    $past = Appointment::factory()->confirmed()->forAnimal($pastAnimal)->at(now()->subDays(3)->startOfHour())->create();

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(ListAppointments::class)
        ->filterTable('upcoming')
        ->assertCanSeeTableRecords([$future])
        ->assertCanNotSeeTableRecords([$past]);
});

it('lets a volunteer view the appointment list but hides the manage actions', function () {
    $orgA = Organization::factory()->verified()->create();
    $appointment = shelterAppointmentFor($orgA, 'requested');

    // Il volontario ha solo AppointmentView (no AppointmentManage).
    $volunteer = member($orgA, MembershipRole::Volunteer);
    actAsInTenant($volunteer, $orgA);

    Livewire::test(ListAppointments::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$appointment])
        ->assertActionHidden(TestAction::make('confirm')->table($appointment))
        ->assertActionHidden(TestAction::make('cancel')->table($appointment));

    // Difesa in profondità: le azioni non sono solo nascoste, sono negate a
    // livello di ability (il volontario non ha AppointmentManage).
    expect($volunteer->can('update', $appointment))->toBeFalse()
        ->and($volunteer->can('cancel', $appointment))->toBeFalse();
});

it('lets staff view an appointment of their own org', function () {
    $orgA = Organization::factory()->verified()->create();
    $appointment = shelterAppointmentFor($orgA, 'requested');

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(ViewAppointment::class, ['record' => $appointment->getKey()])
        ->assertOk();
});

it('forbids viewing an appointment that belongs to another tenant (record binding respects tenancy)', function () {
    $orgA = Organization::factory()->verified()->create();
    $orgB = Organization::factory()->verified()->create();

    $appointmentB = shelterAppointmentFor($orgB, 'requested');

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    // Aprire nel tenant A la view di un appuntamento di B non lo risolve: il record
    // è fuori dallo scope del tenant corrente (404). È l'isolamento più forte.
    Livewire::test(ViewAppointment::class, ['record' => $appointmentB->getKey()])
        ->assertNotFound();
});

it('does not let staff of org A confirm an appointment of org B (tenant isolation on actions)', function () {
    $orgA = Organization::factory()->verified()->create();
    $orgB = Organization::factory()->verified()->create();

    $appointmentB = shelterAppointmentFor($orgB, 'requested');

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    // La policy update richiede membership verificata dell'ente proprietario (B):
    // lo staff di A non è autorizzato sull'appuntamento di B.
    expect($staff->can('update', $appointmentB))->toBeFalse()
        ->and($staff->can('cancel', $appointmentB))->toBeFalse();
});
