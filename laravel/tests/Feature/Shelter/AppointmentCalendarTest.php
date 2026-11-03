<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Enums\MembershipRole;
use App\Filament\Shelter\Pages\AppointmentCalendar;
use App\Models\AdoptionApplication;
use App\Models\Animal;
use App\Models\Appointment;
use App\Models\FosterApplication;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\AppointmentConfirmed;
use App\Notifications\AppointmentRescheduled;
use Carbon\CarbonInterface;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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

/*
|--------------------------------------------------------------------------
| Cal-2: interattività (drag-drop reschedule + create-by-click)
|--------------------------------------------------------------------------
*/

it('reports manageable for staff and read-only (not manageable) for a volunteer', function () {
    $orgA = Organization::factory()->verified()->create();

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);
    expect(Livewire::test(AppointmentCalendar::class)->instance()->canManageAppointments())->toBeTrue();

    $volunteer = member($orgA, MembershipRole::Volunteer);
    actAsInTenant($volunteer, $orgA);
    expect(Livewire::test(AppointmentCalendar::class)->instance()->canManageAppointments())->toBeFalse();
});

it('moves an appointment as staff via reschedule() and returns true', function () {
    $orgA = Organization::factory()->verified()->create();
    $appointment = calendarAppointmentFor($orgA, 'confirmed', now()->addDays(3)->startOfHour());

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    $to = now()->addDays(6)->startOfHour();

    $result = Livewire::test(AppointmentCalendar::class)
        ->instance()
        ->reschedule($appointment->getKey(), $to->toIso8601String());

    expect($result)->toBeTrue()
        ->and($appointment->refresh()->scheduled_at->equalTo($to))->toBeTrue();

    Notification::assertSentToTimes($appointment->user, AppointmentRescheduled::class, 1);
});

it('denies reschedule to a volunteer (authorization) and leaves the slot unchanged', function () {
    $orgA = Organization::factory()->verified()->create();
    $when = now()->addDays(3)->startOfHour();
    $appointment = calendarAppointmentFor($orgA, 'confirmed', $when);

    $volunteer = member($orgA, MembershipRole::Volunteer);
    actAsInTenant($volunteer, $orgA);

    $instance = Livewire::test(AppointmentCalendar::class)->instance();

    expect(fn () => $instance->reschedule($appointment->getKey(), now()->addDays(6)->startOfHour()->toIso8601String()))
        ->toThrow(AuthorizationException::class);

    expect($appointment->refresh()->scheduled_at->equalTo($when))->toBeTrue();
});

it('returns false (client reverts) when rescheduling into an overlapping slot, with no state change', function () {
    $orgA = Organization::factory()->verified()->create();
    $animal = Animal::factory()->available()->forOrganization($orgA)->create();

    $when = now()->addDays(3)->startOfHour();
    $moving = Appointment::factory()->forAnimal($animal)->confirmed()->at($when)->create();

    // Un altro appuntamento attivo dello stesso animale occupa il target.
    $blockerAt = now()->addDays(4)->startOfHour();
    Appointment::factory()->forAnimal($animal)->confirmed()->at($blockerAt)->create();

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    $result = Livewire::test(AppointmentCalendar::class)
        ->instance()
        ->reschedule($moving->getKey(), $blockerAt->copy()->addMinutes(30)->toIso8601String());

    expect($result)->toBeFalse()
        ->and($moving->refresh()->scheduled_at->equalTo($when))->toBeTrue();
});

it('does not reschedule an appointment of another tenant (404)', function () {
    $orgA = Organization::factory()->verified()->create();
    $orgB = Organization::factory()->verified()->create();
    $appointmentB = calendarAppointmentFor($orgB, 'confirmed');

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    $instance = Livewire::test(AppointmentCalendar::class)->instance();

    // La risoluzione è vincolata a organization_id = tenant corrente: un id di un altro
    // ente non viene trovato → findOrFail solleva ModelNotFoundException (che nel ciclo
    // HTTP diventa un 404). Nessuno spostamento cross-tenant.
    expect(fn () => $instance->reschedule($appointmentB->getKey(), now()->addDays(6)->startOfHour()->toIso8601String()))
        ->toThrow(ModelNotFoundException::class);

    expect($appointmentB->refresh()->scheduled_at->equalTo(now()->addDays(2)->startOfHour()))->toBeTrue();
});

it('creates an appointment for the tenant via the create action', function () {
    $orgA = Organization::factory()->verified()->create();
    $animal = Animal::factory()->available()->forOrganization($orgA)->create();

    // Un richiedente già in relazione con l'ente (ha un appuntamento passato con esso).
    $requester = User::factory()->create();
    Appointment::factory()->forAnimal($animal)->forUser($requester)->completed()->create();

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    $when = now()->addDays(5)->startOfHour();

    Livewire::test(AppointmentCalendar::class)
        ->callAction('create', data: [
            'animal_id' => $animal->getKey(),
            'user_id' => $requester->getKey(),
            'type' => AppointmentType::Visit->value,
            'scheduled_at' => $when->toDateTimeString(),
            'notes' => 'Prima visita.',
        ])
        ->assertHasNoActionErrors()
        ->assertDispatched('calendar-refresh');

    $created = Appointment::query()
        ->where('organization_id', $orgA->getKey())
        ->where('user_id', $requester->getKey())
        ->where('animal_id', $animal->getKey())
        ->where('status', AppointmentStatus::Requested)
        ->first();

    expect($created)->not->toBeNull()
        ->and($created->scheduled_at->equalTo($when))->toBeTrue()
        ->and($created->notes)->toBe('Prima visita.');
});

it('scopes the create requester options to org-related users only (GDPR)', function () {
    $orgA = Organization::factory()->verified()->create();
    $animal = Animal::factory()->available()->forOrganization($orgA)->create();

    // Tre relazioni diverse con l'ente → tutti selezionabili.
    $viaAppointment = User::factory()->create();
    Appointment::factory()->forAnimal($animal)->forUser($viaAppointment)->completed()->create();

    $viaAdoption = User::factory()->create();
    AdoptionApplication::factory()->forOrganization($orgA)->forAdopter($viaAdoption)->create();

    $viaFoster = User::factory()->create();
    FosterApplication::factory()->forOrganization($orgA)->forAdopter($viaFoster)->create();

    // Utente TOTALMENTE estraneo all'ente → NON selezionabile.
    $unrelated = User::factory()->create();

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    $reflection = new ReflectionMethod(AppointmentCalendar::class, 'relatedRequesterOptions');
    $reflection->setAccessible(true);
    $options = $reflection->invoke(Livewire::test(AppointmentCalendar::class)->instance());

    expect(array_keys($options))
        ->toContain($viaAppointment->getKey())
        ->toContain($viaAdoption->getKey())
        ->toContain($viaFoster->getKey())
        ->not->toContain($unrelated->getKey());
});

it('rejects creating an appointment for another tenant animal (tenant isolation backstop)', function () {
    $orgA = Organization::factory()->verified()->create();
    $orgB = Organization::factory()->verified()->create();

    // Animale di un ALTRO ente: non è tra le opzioni del tenant A.
    $foreignAnimal = Animal::factory()->available()->forOrganization($orgB)->create();

    $requester = User::factory()->create();
    // Rendiamo il richiedente in relazione con A, così solo l'animale è fuori scope.
    $animalA = Animal::factory()->available()->forOrganization($orgA)->create();
    Appointment::factory()->forAnimal($animalA)->forUser($requester)->completed()->create();

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(AppointmentCalendar::class)
        ->callAction('create', data: [
            'animal_id' => $foreignAnimal->getKey(),
            'user_id' => $requester->getKey(),
            'type' => AppointmentType::Visit->value,
            'scheduled_at' => now()->addDays(5)->startOfHour()->toDateTimeString(),
        ]);

    // Backstop: nessun appuntamento creato per l'animale di un altro ente.
    expect(Appointment::query()->where('animal_id', $foreignAnimal->getKey())->count())->toBe(0);
});

it('rejects creating an appointment for a user not related to the org (GDPR backstop)', function () {
    $orgA = Organization::factory()->verified()->create();
    $animal = Animal::factory()->available()->forOrganization($orgA)->create();

    // Utente NON in relazione con l'ente: fuori dall'allow-list dei richiedenti.
    $unrelated = User::factory()->create();

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(AppointmentCalendar::class)
        ->callAction('create', data: [
            'animal_id' => $animal->getKey(),
            'user_id' => $unrelated->getKey(),
            'type' => AppointmentType::Visit->value,
            'scheduled_at' => now()->addDays(5)->startOfHour()->toDateTimeString(),
        ]);

    // Backstop server-side: nessun appuntamento creato per un utente estraneo,
    // anche se l'id fosse forzato oltre la select (l'opzione non è nemmeno esposta).
    expect(Appointment::query()->where('user_id', $unrelated->getKey())->count())->toBe(0);
});

it('denies the create action to a volunteer server-side (not just a read-only UI)', function () {
    $orgA = Organization::factory()->verified()->create();
    $animal = Animal::factory()->available()->forOrganization($orgA)->create();

    $requester = User::factory()->create();
    Appointment::factory()->forAnimal($animal)->forUser($requester)->completed()->create();

    // Il volontario ha solo AppointmentView: niente gestione, niente creazione.
    $volunteer = member($orgA, MembershipRole::Volunteer);
    actAsInTenant($volunteer, $orgA);

    // Gate UI: non può gestire (il calendario è read-only lato client).
    expect(Livewire::test(AppointmentCalendar::class)->instance()->canManageAppointments())
        ->toBeFalse();

    // Gate server: l'azione create è AUTORIZZATA su canManageAppointments, quindi per il
    // volontario è nascosta (authorize=false ⇒ non montabile), non solo assente dalla UI.
    Livewire::test(AppointmentCalendar::class)
        ->assertActionHidden(TestAction::make('create'));

    // Nessun appuntamento creato: l'azione non è nemmeno raggiungibile.
    expect(Appointment::query()->where('status', AppointmentStatus::Requested)->count())->toBe(0);
});
