<?php

declare(strict_types=1);

use App\Enums\AppointmentType;
use App\Models\Animal;
use App\Models\Appointment;
use App\Models\Organization;
use App\Models\User;
use App\Support\Calendar\AppointmentEventMapper;
use App\Support\Calendar\CalendarEventData;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Appuntamento deterministico per un animale dell'ente, con richiedente noto (per
 * asserire che i suoi dati personali NON compaiano nel VO minimizzato).
 */
function mapperAppointment(
    Organization $organization,
    string $state = 'confirmed',
    string $animalName = 'Rex',
    ?User $requester = null,
): Appointment {
    $animal = Animal::factory()->forOrganization($organization)->create(['name' => $animalName]);

    $factory = Appointment::factory()
        ->{$state}()
        ->ofType(AppointmentType::Visit)
        ->forAnimal($animal)
        ->at(now()->addDays(3)->startOfHour());

    if ($requester !== null) {
        $factory = $factory->forUser($requester);
    }

    return $factory->create();
}

it('produces a minimized event DTO with a localized summary and location', function () {
    app()->setLocale('it');

    $org = Organization::factory()->create(['name' => 'Rifugio Speranza', 'city' => 'Milano']);
    $appointment = mapperAppointment($org, 'confirmed', 'Rex');

    $event = app(AppointmentEventMapper::class)->toEventData($appointment);

    expect($event)->toBeInstanceOf(CalendarEventData::class)
        ->and($event->summary)->toBe('Visita: Rex')
        ->and($event->location)->toBe('Rifugio Speranza, Milano')
        ->and($event->description)->toContain('Rex')
        ->and($event->appointmentId)->toBe($appointment->getKey());
});

it('never leaks the street address into the location', function () {
    $org = Organization::factory()->create([
        'name' => 'Rifugio Speranza',
        'city' => 'Milano',
        'address_line' => 'Via Segreta 123',
    ]);
    $appointment = mapperAppointment($org);

    $event = app(AppointmentEventMapper::class)->toEventData($appointment);

    expect($event->location)
        ->not->toContain('Via Segreta')
        ->not->toContain('123');
});

it('excludes all requester personal data from the DTO (PII minimization)', function () {
    $requester = User::factory()->create([
        'name' => 'Mariapia Verdilunghi',
        'email' => 'mariapia.verdilunghi@example.test',
    ]);

    $org = Organization::factory()->create();
    $appointment = mapperAppointment($org, 'confirmed', 'Rex', $requester);

    $event = app(AppointmentEventMapper::class)->toEventData($appointment);

    // Serializziamo l'intero VO per verificare che nessun campo trasporti PII.
    $serialized = json_encode([
        $event->uid,
        $event->summary,
        $event->location,
        $event->description,
        $event->status,
    ]);

    expect($serialized)
        ->not->toContain('mariapia.verdilunghi@example.test')
        ->not->toContain('Verdilunghi')
        ->not->toContain('Mariapia');
});

it('builds a stable deterministic UID per appointment', function () {
    $org = Organization::factory()->create();
    $appointment = mapperAppointment($org);

    $mapper = app(AppointmentEventMapper::class);

    $first = $mapper->toEventData($appointment)->uid;
    $second = $mapper->toEventData($appointment->fresh())->uid;

    expect($first)->toStartWith('appointment-'.$appointment->getKey().'@')
        ->and($first)->toBe($second);
});

it('sets end to start plus the domain slot length', function () {
    $org = Organization::factory()->create();
    $start = now()->addDays(5)->startOfHour();
    $animal = Animal::factory()->forOrganization($org)->create();
    $appointment = Appointment::factory()->confirmed()->forAnimal($animal)->at($start)->create();

    $event = app(AppointmentEventMapper::class)->toEventData($appointment);

    expect($event->start->equalTo($start))->toBeTrue()
        ->and($event->end->equalTo($start->copy()->addMinutes(Appointment::SLOT_MINUTES)))->toBeTrue();
});

it('maps appointment status to the neutral event status', function (string $state, string $expected) {
    $org = Organization::factory()->create();
    $appointment = mapperAppointment($org, $state);

    $event = app(AppointmentEventMapper::class)->toEventData($appointment);

    expect($event->status)->toBe($expected);
})->with([
    'requested → tentative' => ['requested', 'tentative'],
    'confirmed → confirmed' => ['confirmed', 'confirmed'],
    'completed → confirmed' => ['completed', 'confirmed'],
    'cancelled → cancelled' => ['cancelled', 'cancelled'],
    'no_show → confirmed' => ['noShow', 'confirmed'],
]);
