<?php

declare(strict_types=1);

use App\Enums\AppointmentType;
use App\Models\Animal;
use App\Models\Appointment;
use App\Models\Organization;
use App\Models\User;
use App\Support\Calendar\AppointmentCalendarExport;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Appuntamento deterministico per un animale dell'ente, con richiedente noto (per
 * asserire che i suoi dati personali NON compaiano nell'ICS).
 */
function exportAppointment(
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

it('serializes a single appointment as a valid VEVENT with a localized minimized summary', function () {
    app()->setLocale('it');

    $org = Organization::factory()->create(['name' => 'Rifugio Speranza', 'city' => 'Milano']);
    $appointment = exportAppointment($org, 'confirmed', 'Rex');

    $ics = app(AppointmentCalendarExport::class)->single($appointment);

    expect($ics)
        ->toContain('BEGIN:VCALENDAR')
        ->toContain('VERSION:2.0')
        ->toContain('BEGIN:VEVENT')
        ->toContain('END:VEVENT')
        ->toContain('END:VCALENDAR')
        // SUMMARY minimizzato "{tipo}: {animale}" (localizzato IT: "Visita").
        ->toContain('SUMMARY:Visita: Rex')
        ->toContain('STATUS:CONFIRMED');
});

it('uses a stable deterministic UID per appointment', function () {
    $org = Organization::factory()->create();
    $appointment = exportAppointment($org);

    $export = app(AppointmentCalendarExport::class);

    $first = $export->single($appointment);
    $second = $export->single($appointment->fresh());

    $uid = 'appointment-'.$appointment->getKey().'@';

    expect($first)->toContain('UID:'.$uid)
        ->and($second)->toContain('UID:'.$uid);

    // Lo stesso UID a fetch ripetuti: i client aggiornano invece di duplicare.
    preg_match('/UID:(.+)/', $first, $m1);
    preg_match('/UID:(.+)/', $second, $m2);

    expect(trim($m1[1]))->toBe(trim($m2[1]));
});

it('sets DTEND to DTSTART plus the domain slot length', function () {
    $org = Organization::factory()->create();
    $start = now()->addDays(5)->startOfHour();
    $animal = Animal::factory()->forOrganization($org)->create();
    $appointment = Appointment::factory()->confirmed()->forAnimal($animal)->at($start)->create();

    $ics = app(AppointmentCalendarExport::class)->single($appointment);

    $expectedStart = $start->copy()->utc()->format('Ymd\THis\Z');
    $expectedEnd = $start->copy()->addMinutes(Appointment::SLOT_MINUTES)->utc()->format('Ymd\THis\Z');

    expect($ics)
        ->toContain('DTSTART:'.$expectedStart)
        ->toContain('DTEND:'.$expectedEnd);
});

it('sets LOCATION to organization name and city but never the street address', function () {
    $org = Organization::factory()->create([
        'name' => 'Rifugio Speranza',
        'city' => 'Milano',
        'address_line' => 'Via Segreta 123',
    ]);
    $appointment = exportAppointment($org);

    $ics = app(AppointmentCalendarExport::class)->single($appointment);

    expect($ics)
        ->toContain('LOCATION:Rifugio Speranza')
        ->toContain('Milano')
        // L'indirizzo civico non deve MAI comparire.
        ->not->toContain('Via Segreta')
        ->not->toContain('123');
});

it('excludes all requester personal data from the event (PII minimization)', function () {
    $requester = User::factory()->create([
        'name' => 'Mariapia Verdilunghi',
        'email' => 'mariapia.verdilunghi@example.test',
    ]);

    $org = Organization::factory()->create(['name' => 'Rifugio Speranza', 'city' => 'Milano']);
    $appointment = exportAppointment($org, 'confirmed', 'Rex', $requester);

    $ics = app(AppointmentCalendarExport::class)->single($appointment);

    expect($ics)
        ->not->toContain('mariapia.verdilunghi@example.test')
        ->not->toContain('Verdilunghi')
        ->not->toContain('Mariapia');
});

it('maps appointment status to the iCalendar STATUS values', function (string $state, string $expected) {
    $org = Organization::factory()->create();
    $appointment = exportAppointment($org, $state);

    $ics = app(AppointmentCalendarExport::class)->single($appointment);

    expect($ics)->toContain('STATUS:'.$expected);
})->with([
    'requested → TENTATIVE' => ['requested', 'TENTATIVE'],
    'confirmed → CONFIRMED' => ['confirmed', 'CONFIRMED'],
    'completed → CONFIRMED' => ['completed', 'CONFIRMED'],
    'cancelled → CANCELLED' => ['cancelled', 'CANCELLED'],
    'no_show → CONFIRMED' => ['noShow', 'CONFIRMED'],
]);

it('builds a feed with one VEVENT per in-window appointment of the organization', function () {
    $org = Organization::factory()->create(['name' => 'Rifugio Speranza']);
    exportAppointment($org, 'confirmed', 'Rex');
    exportAppointment($org, 'requested', 'Luna');

    $ics = app(AppointmentCalendarExport::class)->feed($org);

    expect(substr_count($ics, 'BEGIN:VEVENT'))->toBe(2)
        ->and(substr_count($ics, 'END:VEVENT'))->toBe(2)
        ->and($ics)->toContain('SUMMARY:Visita: Rex')
        ->and($ics)->toContain('SUMMARY:Visita: Luna');
});

it('includes cancelled appointments in the feed with STATUS:CANCELLED', function () {
    $org = Organization::factory()->create();
    exportAppointment($org, 'cancelled', 'Rex');

    $ics = app(AppointmentCalendarExport::class)->feed($org);

    expect($ics)
        ->toContain('BEGIN:VEVENT')
        ->toContain('STATUS:CANCELLED');
});

it('scopes the feed to the organization (no cross-tenant leakage)', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();

    exportAppointment($orgA, 'confirmed', 'AlphaDog');
    exportAppointment($orgB, 'confirmed', 'BetaDog');

    $ics = app(AppointmentCalendarExport::class)->feed($orgA);

    expect($ics)
        ->toContain('AlphaDog')
        ->not->toContain('BetaDog');
});

it('excludes appointments outside the feed window', function () {
    $org = Organization::factory()->create();

    $inWindow = exportAppointment($org, 'confirmed', 'InWindowDog');

    // Fuori finestra: oltre 365 giorni nel futuro.
    $farAnimal = Animal::factory()->forOrganization($org)->create(['name' => 'FarFutureDog']);
    Appointment::factory()->confirmed()->forAnimal($farAnimal)->at(now()->addDays(400)->startOfHour())->create();

    $ics = app(AppointmentCalendarExport::class)->feed($org);

    expect($ics)
        ->toContain('InWindowDog')
        ->not->toContain('FarFutureDog')
        ->and($inWindow)->not->toBeNull();
});
