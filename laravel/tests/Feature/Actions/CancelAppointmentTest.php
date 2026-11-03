<?php

declare(strict_types=1);

use App\Actions\Appointments\CancelAppointment;
use App\Actions\Organizations\AddOrganizationMember;
use App\Enums\AppointmentStatus;
use App\Enums\MembershipRole;
use App\Exceptions\InvalidAppointmentTransition;
use App\Models\Animal;
use App\Models\Appointment;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\AppointmentCancelled;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

afterEach(function () {
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

beforeEach(function () {
    Notification::fake();
});

/**
 * Ente verificato con un membro staff verificato e un animale dell'ente, con un
 * appuntamento confermato di un richiedente esterno.
 *
 * @return array{staff: User, requester: User, appointment: Appointment}
 */
function confirmedAppointmentWithStaff(): array
{
    // AddOrganizationMember assegna un ruolo spatie: i ruoli devono esistere.
    test()->seed(RolesAndPermissionsSeeder::class);

    $organization = Organization::factory()->verified()->create();

    $staff = User::factory()->create();
    app(AddOrganizationMember::class)->handle($organization, $staff, MembershipRole::Staff, verified: true);

    $requester = User::factory()->create();
    $animal = Animal::factory()->forOrganization($organization)->create();
    $appointment = Appointment::factory()
        ->forAnimal($animal)
        ->forOrganization($organization)
        ->forUser($requester)
        ->confirmed()
        ->create();

    return ['staff' => $staff, 'requester' => $requester, 'appointment' => $appointment];
}

it('cancels a requested appointment persisting and logging with reason and actor', function () {
    $requester = User::factory()->create();
    $appointment = Appointment::factory()->forUser($requester)->requested()->create();

    $result = app(CancelAppointment::class)->handle($appointment, $requester, 'Imprevisto.');

    expect($result->status)->toBe(AppointmentStatus::Cancelled)
        ->and($appointment->fresh()->status)->toBe(AppointmentStatus::Cancelled);

    $activity = Activity::query()->where('description', 'appointment.cancelled')->latest('id')->first();
    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($requester->getKey())
        ->and($activity->properties->get('reason'))->toBe('Imprevisto.');
});

it('cancels a confirmed appointment (Confirmed is not terminal)', function () {
    $requester = User::factory()->create();
    $appointment = Appointment::factory()->forUser($requester)->confirmed()->create();

    app(CancelAppointment::class)->handle($appointment, $requester);

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Cancelled);
});

it('notifies the org staff (counterparty) when the requester cancels', function () {
    ['staff' => $staff, 'requester' => $requester, 'appointment' => $appointment] = confirmedAppointmentWithStaff();

    app(CancelAppointment::class)->handle($appointment, $requester);

    // Il richiedente annulla → la controparte (staff verificato) è notificata; il
    // richiedente NON riceve la propria notifica.
    Notification::assertSentTo($staff, AppointmentCancelled::class);
    Notification::assertNotSentTo($requester, AppointmentCancelled::class);
});

it('notifies the requester (counterparty) when the staff cancels', function () {
    ['staff' => $staff, 'requester' => $requester, 'appointment' => $appointment] = confirmedAppointmentWithStaff();

    app(CancelAppointment::class)->handle($appointment, $staff);

    // Lo staff annulla → il richiedente è notificato; lo staff NON riceve nulla.
    Notification::assertSentTo($requester, AppointmentCancelled::class);
    Notification::assertNotSentTo($staff, AppointmentCancelled::class);
});

it('throws InvalidAppointmentTransition cancelling a completed appointment and changes nothing', function () {
    $requester = User::factory()->create();
    $appointment = Appointment::factory()->forUser($requester)->completed()->create();

    expect(fn () => app(CancelAppointment::class)->handle($appointment, $requester))
        ->toThrow(InvalidAppointmentTransition::class);

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Completed)
        ->and(Activity::query()->where('description', 'appointment.cancelled')->count())->toBe(0);

    Notification::assertNothingSent();
});

it('throws InvalidAppointmentTransition cancelling an already cancelled appointment', function () {
    $requester = User::factory()->create();
    $appointment = Appointment::factory()->forUser($requester)->cancelled()->create();

    expect(fn () => app(CancelAppointment::class)->handle($appointment, $requester))
        ->toThrow(InvalidAppointmentTransition::class);

    Notification::assertNothingSent();
});
