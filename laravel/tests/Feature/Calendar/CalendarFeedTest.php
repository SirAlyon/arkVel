<?php

declare(strict_types=1);

use App\Enums\AppointmentType;
use App\Models\Animal;
use App\Models\Appointment;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Appuntamento confermato per un animale dell'ente, con richiedente noto per le
 * asserzioni di minimizzazione (i suoi dati NON devono comparire nel feed).
 */
function feedAppointment(
    Organization $organization,
    string $animalName = 'Rex',
    ?User $requester = null,
    string $state = 'confirmed',
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

it('serves the feed with a valid token as text/calendar containing the org events', function () {
    $org = Organization::factory()->create(['name' => 'Rifugio Speranza', 'city' => 'Milano']);
    $token = $org->ensureCalendarFeedToken();
    feedAppointment($org, 'Rex');

    $response = $this->get(route('calendar.feed', ['organization' => $org->slug, 'token' => $token]));

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/calendar');

    $body = $response->getContent();

    expect($body)
        ->toContain('BEGIN:VCALENDAR')
        ->toContain('BEGIN:VEVENT')
        // UID stabile e deterministico.
        ->toContain('UID:appointment-')
        // SUMMARY minimizzato.
        ->toContain('SUMMARY:');
});

it('resolves the organization by id as well as by slug', function () {
    $org = Organization::factory()->create();
    $token = $org->ensureCalendarFeedToken();
    feedAppointment($org, 'Rex');

    $this->get(route('calendar.feed', ['organization' => (string) $org->getKey(), 'token' => $token]))
        ->assertOk();
});

it('returns 404 for a wrong token without revealing the organization', function () {
    $org = Organization::factory()->create();
    $org->ensureCalendarFeedToken();

    $this->get(route('calendar.feed', ['organization' => $org->slug, 'token' => 'not-the-real-token']))
        ->assertNotFound();
});

it('returns 404 when the organization has no feed token yet', function () {
    $org = Organization::factory()->create();

    expect($org->calendar_feed_token)->toBeNull();

    $this->get(route('calendar.feed', ['organization' => $org->slug, 'token' => 'anything']))
        ->assertNotFound();
});

it('returns 404 for an empty token segment', function () {
    $org = Organization::factory()->create();
    $org->ensureCalendarFeedToken();

    // Un token vuoto non risolve la rotta (parametro mancante) → 404.
    $this->get('/calendar/'.$org->slug.'/')
        ->assertNotFound();
});

it('does not include another organization appointments in the feed (isolation)', function () {
    $orgA = Organization::factory()->create();
    $orgB = Organization::factory()->create();
    $tokenA = $orgA->ensureCalendarFeedToken();

    feedAppointment($orgA, 'AlphaDog');
    feedAppointment($orgB, 'BetaDog');

    $body = $this->get(route('calendar.feed', ['organization' => $orgA->slug, 'token' => $tokenA]))
        ->assertOk()
        ->getContent();

    expect($body)
        ->toContain('AlphaDog')
        ->not->toContain('BetaDog');
});

it('includes cancelled appointments in the feed with STATUS:CANCELLED', function () {
    $org = Organization::factory()->create();
    $token = $org->ensureCalendarFeedToken();
    feedAppointment($org, 'Rex', state: 'cancelled');

    $body = $this->get(route('calendar.feed', ['organization' => $org->slug, 'token' => $token]))
        ->assertOk()
        ->getContent();

    expect($body)->toContain('STATUS:CANCELLED');
});

it('does not leak requester email, phone or surname in the feed (PII minimization)', function () {
    $requester = User::factory()->create([
        'name' => 'Mariapia Verdilunghi',
        'email' => 'mariapia.verdilunghi@example.test',
    ]);

    $org = Organization::factory()->create(['name' => 'Rifugio Speranza', 'city' => 'Milano']);
    $token = $org->ensureCalendarFeedToken();
    feedAppointment($org, 'Rex', $requester);

    $body = $this->get(route('calendar.feed', ['organization' => $org->slug, 'token' => $token]))
        ->assertOk()
        ->getContent();

    expect($body)
        ->not->toContain('mariapia.verdilunghi@example.test')
        ->not->toContain('Verdilunghi')
        ->not->toContain('Mariapia');
});

it('revokes the old URL and serves the new one when the token is regenerated', function () {
    $org = Organization::factory()->create();
    $oldToken = $org->ensureCalendarFeedToken();
    feedAppointment($org, 'Rex');

    // Vecchio URL: 200 prima della rigenerazione.
    $this->get(route('calendar.feed', ['organization' => $org->slug, 'token' => $oldToken]))
        ->assertOk();

    $newToken = $org->regenerateCalendarFeedToken();

    expect($newToken)->not->toBe($oldToken);

    // Vecchio URL: ora 404 (revocato).
    $this->get(route('calendar.feed', ['organization' => $org->slug, 'token' => $oldToken]))
        ->assertNotFound();

    // Nuovo URL: 200.
    $this->get(route('calendar.feed', ['organization' => $org->slug, 'token' => $newToken]))
        ->assertOk();
});
