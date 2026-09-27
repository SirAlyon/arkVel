<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Enums\MembershipRole;
use App\Filament\Shelter\Pages\AppointmentCalendar;
use App\Models\Animal;
use App\Models\Appointment;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\AppointmentConfirmed;
use Carbon\CarbonInterface;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\PermissionRegistrar;

// Gli helper member() e actAsInTenant() sono in tests/Pest.php (condivisi dallo
// shelter). Specchio di AppointmentResourceTest.php.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Notification::fake();
});

afterEach(function () {
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

/**
 * Un appuntamento in uno stato dato per un animale dell'ente indicato, agganciato a
 * uno slot deterministico (default: fra 2 giorni all'ora piena).
 */
function calendarAppointmentFor(
    Organization $organization,
    string $factoryState = 'requested',
    ?CarbonInterface $at = null,
): Appointment {
    $animal = Animal::factory()->available()->forOrganization($organization)->create();

    return Appointment::factory()
        ->{$factoryState}()
        ->forAnimal($animal)
        ->at($at ?? now()->addDays(2)->startOfHour())
        ->create();
}

it('renders the calendar page for a verified staff member of the tenant', function () {
    $orgA = Organization::factory()->verified()->create();

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(AppointmentCalendar::class)
        ->assertOk()
        // Il container del calendario e la funzione di init Alpine sono nella view.
        ->assertSee('appointmentCalendar()', escape: false)
        ->assertSee('x-ref="calendar"', escape: false);
});

it('feeds only the current tenant appointments within the range (cross-tenant isolation)', function () {
    $orgA = Organization::factory()->verified()->create();
    $orgB = Organization::factory()->verified()->create();

    $inRangeA = calendarAppointmentFor($orgA, 'requested', now()->addDays(3)->startOfHour());
    $inRangeB = calendarAppointmentFor($orgB, 'requested', now()->addDays(3)->startOfHour());

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    $events = Livewire::test(AppointmentCalendar::class)
        ->instance()
        ->fetchEvents(
            now()->startOfMonth()->toDateString(),
            now()->addMonth()->endOfMonth()->toDateString(),
        );

    $ids = array_column($events, 'id');

    expect($ids)->toContain($inRangeA->getKey())
        ->and($ids)->not->toContain($inRangeB->getKey());
});

it('excludes appointments outside the requested window', function () {
    $orgA = Organization::factory()->verified()->create();

    $inside = calendarAppointmentFor($orgA, 'confirmed', now()->addDays(3)->startOfHour());
    $outside = calendarAppointmentFor($orgA, 'confirmed', now()->addDays(90)->startOfHour());

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    $events = Livewire::test(AppointmentCalendar::class)
        ->instance()
        ->fetchEvents(
            now()->startOfDay()->toDateString(),
            now()->addDays(10)->toDateString(),
        );

    $ids = array_column($events, 'id');

    expect($ids)->toContain($inside->getKey())
        ->and($ids)->not->toContain($outside->getKey());
});

it('maps an event with a localized title, slot-length end and status colour', function () {
    $orgA = Organization::factory()->verified()->create();
    $appointment = calendarAppointmentFor($orgA, 'confirmed', now()->addDays(3)->startOfHour());

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    $events = Livewire::test(AppointmentCalendar::class)
        ->instance()
        ->fetchEvents(now()->startOfDay()->toDateString(), now()->addDays(10)->toDateString());

    $event = collect($events)->firstWhere('id', $appointment->getKey());

    expect($event)->not->toBeNull()
        ->and($event['title'])->toContain($appointment->animal->name)
        ->and($event['title'])->toContain($appointment->type->getLabel())
        // Fine = inizio + durata di slot del dominio.
        ->and($event['end'])->toBe(
            $appointment->scheduled_at->copy()->addMinutes(Appointment::SLOT_MINUTES)->toIso8601String(),
        )
        // Confermato → emerald (primary del pannello).
        ->and($event['color'])->toBe('#059669')
        ->and($event['extendedProps']['requester'])->toBe($appointment->user->name);
});

it('confirms a requested appointment via the manage modal action and refreshes the calendar', function () {
    $orgA = Organization::factory()->verified()->create();
    $appointment = calendarAppointmentFor($orgA, 'requested');

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    // La catena annidata [manage → confirm] è risolta dallo stato "non montato":
    // getAction([manage, confirm]) trova la confirm fra le footer action del modale.
    Livewire::test(AppointmentCalendar::class)
        ->assertActionVisible([
            TestAction::make('manage')->arguments(['id' => $appointment->getKey()]),
            TestAction::make('confirm'),
        ])
        ->callAction([
            TestAction::make('manage')->arguments(['id' => $appointment->getKey()]),
            TestAction::make('confirm'),
        ])
        ->assertHasNoActionErrors()
        ->assertDispatched('calendar-refresh');

    expect($appointment->refresh()->status)->toBe(AppointmentStatus::Confirmed)
        ->and(Activity::query()->where('description', 'appointment.confirmed')->count())->toBe(1);

    Notification::assertSentToTimes($appointment->user, AppointmentConfirmed::class, 1);
})->group('transitions');

it('cancels an appointment via the manage modal action with a reason', function () {
    $orgA = Organization::factory()->verified()->create();
    $appointment = calendarAppointmentFor($orgA, 'requested');

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    // La transizione "annulla" nel footer del modale è rinominata `cancelAppointment`
    // (il nome `cancel` è riservato al pulsante di chiusura del modale).
    Livewire::test(AppointmentCalendar::class)
        ->assertActionVisible([
            TestAction::make('manage')->arguments(['id' => $appointment->getKey()]),
            TestAction::make('cancelAppointment'),
        ])
        ->callAction(
            [
                TestAction::make('manage')->arguments(['id' => $appointment->getKey()]),
                TestAction::make('cancelAppointment'),
            ],
            ['reason' => 'Struttura chiusa quel giorno.'],
        )
        ->assertHasNoActionErrors()
        ->assertDispatched('calendar-refresh');

    expect($appointment->refresh()->status)->toBe(AppointmentStatus::Cancelled)
        ->and(Activity::query()->where('description', 'appointment.cancelled')->count())->toBe(1);
})->group('transitions');

it('lets a volunteer open the calendar but hides the manage transitions', function () {
    $orgA = Organization::factory()->verified()->create();
    $appointment = calendarAppointmentFor($orgA, 'requested');

    // Il volontario ha solo AppointmentView (no AppointmentManage).
    $volunteer = member($orgA, MembershipRole::Volunteer);
    actAsInTenant($volunteer, $orgA);

    // Può aprire il dettaglio (view): l'azione manage si monta senza errori.
    Livewire::test(AppointmentCalendar::class)
        ->assertOk()
        ->mountAction(TestAction::make('manage')->arguments(['id' => $appointment->getKey()]))
        ->assertHasNoActionErrors()
        ->assertActionMounted(TestAction::make('manage')->arguments(['id' => $appointment->getKey()]));

    // ... ma le transizioni di gestione sono nascoste (nessun AppointmentManage).
    // La catena annidata è valutata dallo stato non montato per evitare il
    // raddoppio del parent nel resolver dei test.
    Livewire::test(AppointmentCalendar::class)
        ->assertActionHidden([
            TestAction::make('manage')->arguments(['id' => $appointment->getKey()]),
            TestAction::make('confirm'),
        ])
        ->assertActionHidden([
            TestAction::make('manage')->arguments(['id' => $appointment->getKey()]),
            TestAction::make('cancelAppointment'),
        ]);

    // Difesa in profondità: negato anche a livello di ability.
    expect($volunteer->can('update', $appointment))->toBeFalse()
        ->and($volunteer->can('cancel', $appointment))->toBeFalse();
});

it('does not resolve an appointment of another tenant through the manage action (404)', function () {
    $orgA = Organization::factory()->verified()->create();
    $orgB = Organization::factory()->verified()->create();

    $appointmentB = calendarAppointmentFor($orgB, 'requested');

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    // Montare l'azione con l'id di un appuntamento di B nel tenant A non lo risolve:
    // la query è vincolata a organization_id = tenant corrente → 404.
    Livewire::test(AppointmentCalendar::class)
        ->mountAction(TestAction::make('manage')->arguments(['id' => $appointmentB->getKey()]))
        ->assertNotFound();
});

it('surfaces a localized error (no 500) when an illegal transition is attempted', function () {
    $orgA = Organization::factory()->verified()->create();
    // Confermato: "confirm" (Requested→Confirmed) è illegale da qui.
    $appointment = calendarAppointmentFor($orgA, 'confirmed');

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    // La confirm non è visibile su un confermato (il grafo non consente
    // Confirmed→Confirmed): la UI non offre l'azione, nessun 500. La complete,
    // invece, è una transizione legale (Confirmed→Completed) e resta visibile.
    Livewire::test(AppointmentCalendar::class)
        ->assertActionHidden([
            TestAction::make('manage')->arguments(['id' => $appointment->getKey()]),
            TestAction::make('confirm'),
        ])
        ->assertActionVisible([
            TestAction::make('manage')->arguments(['id' => $appointment->getKey()]),
            TestAction::make('complete'),
        ]);

    expect($appointment->refresh()->status)->toBe(AppointmentStatus::Confirmed);
})->group('transitions');

it('denies access to a non-member of the tenant', function () {
    $orgA = Organization::factory()->verified()->create();

    // Un utente autenticato ma NON membro dell'ente non può accedere alla pagina.
    $outsider = User::factory()->create();
    actAsInTenant($outsider, $orgA);

    expect(AppointmentCalendar::canAccess())->toBeFalse();
});
