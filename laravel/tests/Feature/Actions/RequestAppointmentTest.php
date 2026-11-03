<?php

declare(strict_types=1);

use App\Actions\Appointments\RequestAppointment;
use App\Actions\Organizations\AddOrganizationMember;
use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Enums\MembershipRole;
use App\Exceptions\AppointmentSlotUnavailable;
use App\Exceptions\InvalidAppointmentSlot;
use App\Models\Animal;
use App\Models\Appointment;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\NewAppointmentRequested;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

afterEach(function () {
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

/**
 * Un ente verificato con due membri VERIFICATI (destinatari delle notifiche) e uno
 * NON verificato (che non deve ricevere nulla).
 *
 * @return array{organization: Organization, verified: array<int, User>, unverified: User}
 */
function orgWithMixedAppointmentStaff(): array
{
    $organization = Organization::factory()->verified()->create();

    $verified = collect([MembershipRole::Owner, MembershipRole::Staff])
        ->map(function (MembershipRole $role) use ($organization): User {
            $user = User::factory()->create();
            app(AddOrganizationMember::class)->handle($organization, $user, $role, verified: true);

            return $user;
        })
        ->all();

    $unverified = User::factory()->create();
    app(AddOrganizationMember::class)->handle($organization, $unverified, MembershipRole::Staff, verified: false);

    return [
        'organization' => $organization,
        'verified' => $verified,
        'unverified' => $unverified,
    ];
}

it('creates a requested appointment and logs appointment.requested caused by the requester', function () {
    Notification::fake();

    $requester = User::factory()->create();
    $animal = Animal::factory()->create();
    $when = now()->addDays(3)->startOfHour();

    $appointment = app(RequestAppointment::class)->handle(
        $requester,
        $animal,
        AppointmentType::Visit,
        $when,
        'Prima visita.',
    );

    expect($appointment->status)->toBe(AppointmentStatus::Requested)
        ->and($appointment->type)->toBe(AppointmentType::Visit)
        ->and($appointment->user_id)->toBe($requester->getKey())
        ->and($appointment->animal_id)->toBe($animal->getKey())
        ->and($appointment->organization_id)->toBe($animal->organization_id)
        ->and($appointment->notes)->toBe('Prima visita.')
        ->and($appointment->scheduled_at->equalTo($when))->toBeTrue();

    $activity = Activity::query()->where('description', 'appointment.requested')->latest('id')->first();
    expect($activity)->not->toBeNull()
        ->and($activity->subject_id)->toBe($appointment->getKey())
        ->and($activity->causer_id)->toBe($requester->getKey());
});

it('notifies the organizations verified members on success and skips unverified ones', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Notification::fake();

    ['organization' => $organization, 'verified' => $verified, 'unverified' => $unverified] = orgWithMixedAppointmentStaff();
    $animal = Animal::factory()->forOrganization($organization)->create();
    $requester = User::factory()->create();

    app(RequestAppointment::class)->handle(
        $requester,
        $animal,
        AppointmentType::Interview,
        now()->addDays(2)->startOfHour(),
    );

    foreach ($verified as $member) {
        Notification::assertSentTo($member, NewAppointmentRequested::class);
    }

    Notification::assertNotSentTo($unverified, NewAppointmentRequested::class);
    Notification::assertSentTimes(NewAppointmentRequested::class, count($verified));
});

it('throws InvalidAppointmentSlot and creates no row for a slot in the past', function () {
    Notification::fake();

    $requester = User::factory()->create();
    $animal = Animal::factory()->create();

    expect(fn () => app(RequestAppointment::class)->handle(
        $requester,
        $animal,
        AppointmentType::Visit,
        now()->subHour(),
    ))->toThrow(InvalidAppointmentSlot::class);

    expect(Appointment::query()->count())->toBe(0);
    Notification::assertNothingSent();
});

it('throws InvalidAppointmentSlot for a slot exactly now (not strictly future)', function () {
    $requester = User::factory()->create();
    $animal = Animal::factory()->create();

    expect(fn () => app(RequestAppointment::class)->handle(
        $requester,
        $animal,
        AppointmentType::Visit,
        now(),
    ))->toThrow(InvalidAppointmentSlot::class);

    expect(Appointment::query()->count())->toBe(0);
});

it('throws AppointmentSlotUnavailable for the animal when an overlapping active appointment exists', function () {
    Notification::fake();

    $animal = Animal::factory()->create();
    $when = now()->addDays(5)->startOfHour();

    // Appuntamento attivo esistente per l'ANIMALE, con un ALTRO richiedente.
    Appointment::factory()->forAnimal($animal)->confirmed()->at($when)->create();

    // Nuovo richiedente, stesso animale, slot che si sovrappone (30 min dopo,
    // dentro la finestra di 60 min).
    $requester = User::factory()->create();

    expect(fn () => app(RequestAppointment::class)->handle(
        $requester,
        $animal,
        AppointmentType::Visit,
        $when->copy()->addMinutes(30),
    ))->toThrow(AppointmentSlotUnavailable::class);

    // Nessuna riga aggiuntiva creata (resta solo la factory).
    expect(Appointment::query()->count())->toBe(1);
    Notification::assertNothingSent();
});

it('throws AppointmentSlotUnavailable for the user when they overlap on a different animal', function () {
    Notification::fake();

    $requester = User::factory()->create();
    $firstAnimal = Animal::factory()->create();
    $secondAnimal = Animal::factory()->create();
    $when = now()->addDays(6)->startOfHour();

    // Appuntamento attivo esistente per il RICHIEDENTE su un primo animale.
    Appointment::factory()->forUser($requester)->forAnimal($firstAnimal)->confirmed()->at($when)->create();

    // Stesso richiedente, animale DIVERSO, slot sovrapposto: la persona non può
    // essere in due posti insieme.
    expect(fn () => app(RequestAppointment::class)->handle(
        $requester,
        $secondAnimal,
        AppointmentType::Visit,
        $when->copy()->addMinutes(15),
    ))->toThrow(AppointmentSlotUnavailable::class);

    expect(Appointment::query()->count())->toBe(1);
});

it('allows a non-overlapping later slot for the same animal', function () {
    Notification::fake();

    $animal = Animal::factory()->create();
    $when = now()->addDays(7)->startOfHour();

    Appointment::factory()->forAnimal($animal)->confirmed()->at($when)->create();

    // Esattamente SLOT_MINUTES dopo: la finestra esistente è
    // [when, when+60), quindi when+60 non si sovrappone (bordo escluso).
    $requester = User::factory()->create();
    $appointment = app(RequestAppointment::class)->handle(
        $requester,
        $animal,
        AppointmentType::Visit,
        $when->copy()->addMinutes(Appointment::SLOT_MINUTES),
    );

    expect($appointment->exists)->toBeTrue()
        ->and(Appointment::query()->count())->toBe(2);
});

it('does not block on a cancelled appointment in the same window', function () {
    Notification::fake();

    $animal = Animal::factory()->create();
    $when = now()->addDays(8)->startOfHour();

    // Un appuntamento ANNULLATO nello stesso slot NON deve bloccare (non è attivo).
    Appointment::factory()->forAnimal($animal)->cancelled()->at($when)->create();

    $requester = User::factory()->create();
    $appointment = app(RequestAppointment::class)->handle(
        $requester,
        $animal,
        AppointmentType::Visit,
        $when,
    );

    expect($appointment->status)->toBe(AppointmentStatus::Requested)
        ->and(Appointment::query()->active()->count())->toBe(1);
});

it('does not block on a completed appointment in the same window', function () {
    Notification::fake();

    $animal = Animal::factory()->create();
    $when = now()->addDays(9)->startOfHour();

    // Un appuntamento COMPLETATO nello stesso slot NON deve bloccare (terminale).
    Appointment::factory()->forAnimal($animal)->completed()->at($when)->create();

    $requester = User::factory()->create();
    $appointment = app(RequestAppointment::class)->handle(
        $requester,
        $animal,
        AppointmentType::Visit,
        $when,
    );

    expect($appointment->exists)->toBeTrue()
        ->and(Appointment::query()->count())->toBe(2);
});
