<?php

declare(strict_types=1);

use App\Actions\Organizations\AddOrganizationMember;
use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Enums\MembershipRole;
use App\Livewire\AnimalDetail;
use App\Models\Animal;
use App\Models\Appointment;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\NewAppointmentRequested;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

// Specchio di tests/Feature/Public/RequestFosterFlowTest.php per il ramo
// prenotazione appuntamento: ospite → login; autenticato → validazione + delega a
// RequestAppointment; slot passato / occupato → feedback in-place, nessuna riga.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Notification::fake();
});

afterEach(function () {
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

/**
 * Un ente verificato con almeno un membro verificato (destinatario delle notifiche)
 * e un animale pubblicamente visibile.
 *
 * @return array{0: Animal, 1: User}
 */
function verifiedOrgAnimalWithAppointmentStaff(): array
{
    $org = Organization::factory()->verified()->create();

    $staff = User::factory()->create();
    app(AddOrganizationMember::class)->handle(
        organization: $org,
        user: $staff,
        role: MembershipRole::Staff,
        verified: true,
    );

    $animal = Animal::factory()->available()->forOrganization($org)->create(['name' => 'Argo']);

    return [$animal, $staff];
}

it('shows the booking CTA on the public animal page', function () {
    [$animal] = verifiedOrgAnimalWithAppointmentStaff();

    $this->get(route('animals.show', $animal))
        ->assertOk()
        ->assertSee(__('appointments.booking.cta_title'));
});

it('redirects a guest to the adopter login and creates no appointment', function () {
    [$animal] = verifiedOrgAnimalWithAppointmentStaff();

    Livewire::test(AnimalDetail::class, ['animal' => $animal])
        ->set('appointmentType', AppointmentType::Visit->value)
        ->set('appointmentScheduledAt', now()->addDays(3)->startOfHour()->format('Y-m-d\TH:i'))
        ->call('book')
        ->assertRedirect(route('login'));

    expect(Appointment::query()->count())->toBe(0)
        ->and(session('url.intended'))->toBe(route('animals.show', $animal));

    Notification::assertNothingSent();
});

it('books a visit and notifies the org staff for an authenticated user', function () {
    [$animal, $staff] = verifiedOrgAnimalWithAppointmentStaff();

    $user = User::factory()->create();
    $when = now()->addDays(3)->startOfHour();

    Livewire::actingAs($user)
        ->test(AnimalDetail::class, ['animal' => $animal])
        ->set('appointmentType', AppointmentType::Visit->value)
        ->set('appointmentScheduledAt', $when->format('Y-m-d\TH:i'))
        ->set('appointmentNotes', 'Arrivo con la famiglia.')
        ->call('book')
        ->assertHasNoErrors()
        ->assertSet('bookingMessageIsError', false)
        ->assertSet('bookingMessage', __('appointments.booking.flash_success'));

    $appointments = Appointment::query()->get();

    expect($appointments)->toHaveCount(1);

    $appointment = $appointments->first();

    expect($appointment->status)->toBe(AppointmentStatus::Requested)
        ->and($appointment->type)->toBe(AppointmentType::Visit)
        ->and($appointment->animal_id)->toBe($animal->getKey())
        ->and($appointment->user_id)->toBe($user->getKey())
        ->and($appointment->organization_id)->toBe($animal->organization_id)
        ->and($appointment->notes)->toBe('Arrivo con la famiglia.');

    Notification::assertSentTo($staff, NewAppointmentRequested::class);
});

it('surfaces a localized error for a past slot and creates no appointment', function () {
    [$animal] = verifiedOrgAnimalWithAppointmentStaff();

    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(AnimalDetail::class, ['animal' => $animal])
        ->set('appointmentType', AppointmentType::Visit->value)
        ->set('appointmentScheduledAt', now()->subDay()->startOfHour()->format('Y-m-d\TH:i'))
        ->call('book')
        ->assertHasNoErrors()
        ->assertSet('bookingMessageIsError', true)
        ->assertSet('bookingMessage', __('appointments.booking.slot_in_past'));

    expect(Appointment::query()->count())->toBe(0);

    Notification::assertNothingSent();
});

it('surfaces a localized error for an overlapping slot and keeps a single appointment', function () {
    [$animal] = verifiedOrgAnimalWithAppointmentStaff();

    $user = User::factory()->create();
    $when = now()->addDays(3)->startOfHour();

    // Un appuntamento attivo dello stesso animale occupa già lo slot.
    Appointment::factory()
        ->requested()
        ->forAnimal($animal)
        ->at($when)
        ->create();

    Livewire::actingAs($user)
        ->test(AnimalDetail::class, ['animal' => $animal])
        ->set('appointmentType', AppointmentType::Visit->value)
        ->set('appointmentScheduledAt', $when->format('Y-m-d\TH:i'))
        ->call('book')
        ->assertHasNoErrors()
        ->assertSet('bookingMessageIsError', true)
        ->assertSet('bookingMessage', __('appointments.booking.slot_unavailable'));

    // Nessuna riga nuova: resta solo quella pre-esistente.
    expect(Appointment::query()->count())->toBe(1);
});

it('validates the booking form and creates no appointment on empty input', function () {
    [$animal] = verifiedOrgAnimalWithAppointmentStaff();

    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(AnimalDetail::class, ['animal' => $animal])
        ->call('book')
        ->assertHasErrors(['appointmentType', 'appointmentScheduledAt']);

    expect(Appointment::query()->count())->toBe(0);

    Notification::assertNothingSent();
});
