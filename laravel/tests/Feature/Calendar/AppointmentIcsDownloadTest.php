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

it('lets verified tenant staff download an appointment ics without a pre-set team context', function () {
    $org = Organization::factory()->create();
    $appointment = icsAppointment($org);

    $staff = member($org, MembershipRole::Staff);

    // CONDIZIONE REALE: la rotta web NON passa dal pannello Filament, quindi nessun
    // team di spatie/permission è impostato (a differenza di una request nel pannello).
    // Il controller DEVE allinearlo da sé all'ente dell'appuntamento, altrimenti lo
    // staff verrebbe respinto con 403 (era il bug). Qui azzeriamo esplicitamente il team.
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);

    $this->actingAs($staff)
        ->get(route('appointments.ics', $appointment))
        ->assertOk();
});

it('forbids staff of another organization from downloading the ics (tenant isolation)', function () {
    $orgA = Organization::factory()->create();
    $appointment = icsAppointment($orgA);

    // Staff di un ALTRO ente: anche col team allineato ad A dal controller, non è
    // membro verificato di A → la Policy nega.
    $orgB = Organization::factory()->create();
    $staffB = member($orgB, MembershipRole::Staff);

    app(PermissionRegistrar::class)->setPermissionsTeamId(null);

    $this->actingAs($staffB)
        ->get(route('appointments.ics', $appointment))
        ->assertForbidden();
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
