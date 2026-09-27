<?php

declare(strict_types=1);

use App\Enums\AppointmentType;
use App\Enums\MembershipRole;
use App\Models\Animal;
use App\Models\Appointment;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

// member() è definito in tests/Pest.php (crea un membro verificato col ruolo dato).

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

afterEach(function () {
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

function icsAppointment(Organization $organization, ?User $owner = null): Appointment
{
    $animal = Animal::factory()->forOrganization($organization)->create(['name' => 'Rex']);

    $factory = Appointment::factory()
        ->confirmed()
        ->ofType(AppointmentType::Visit)
        ->forAnimal($animal)
        ->at(now()->addDays(3)->startOfHour());

    if ($owner !== null) {
        $factory = $factory->forUser($owner);
    }

    return $factory->create();
}

it('lets the owner download their appointment ics as an attachment', function () {
    $owner = User::factory()->create();
    $org = Organization::factory()->create(['name' => 'Rifugio Speranza', 'city' => 'Milano']);
    $appointment = icsAppointment($org, $owner);

    $response = $this->actingAs($owner)
        ->get(route('appointments.ics', $appointment));

    $response->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/calendar')
        ->and($response->headers->get('Content-Disposition'))->toContain('attachment');

    expect($response->getContent())
        ->toContain('BEGIN:VEVENT')
        ->toContain('END:VEVENT')
        ->toContain('SUMMARY:');
});

it('lets verified tenant staff download an appointment ics', function () {
    $org = Organization::factory()->create();
    $appointment = icsAppointment($org);

    $staff = member($org, MembershipRole::Staff);

    // Rotta web fuori dal pannello: allineiamo il team id spatie come fa il middleware.
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($org->getKey());
    $registrar->forgetCachedPermissions();

    $this->actingAs($staff)
        ->get(route('appointments.ics', $appointment))
        ->assertOk();
});

it('forbids a stranger from downloading an appointment ics', function () {
    $org = Organization::factory()->create();
    $appointment = icsAppointment($org);

    $stranger = User::factory()->create();

    $this->actingAs($stranger)
        ->get(route('appointments.ics', $appointment))
        ->assertForbidden();
});

it('redirects a guest to login', function () {
    $org = Organization::factory()->create();
    $appointment = icsAppointment($org);

    $this->get(route('appointments.ics', $appointment))
        ->assertRedirect(route('filament.adopter.auth.login'));
});
