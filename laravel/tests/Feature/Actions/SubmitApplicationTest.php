<?php

declare(strict_types=1);

use App\Actions\Applications\SubmitApplication;
use App\Actions\Organizations\AddOrganizationMember;
use App\Enums\ApplicationStatus;
use App\Enums\MembershipRole;
use App\Exceptions\DuplicateApplication;
use App\Exceptions\IncompleteAdopterProfile;
use App\Models\AdopterProfile;
use App\Models\AdoptionApplication;
use App\Models\Animal;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\NewApplicationReceived;
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
 * Un ente verificato con due membri VERIFICATI (destinatari delle notifiche) e
 * uno NON verificato (che non deve ricevere nulla).
 *
 * @return array{organization: Organization, verified: array<int, User>, unverified: User}
 */
function orgWithMixedStaff(): array
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

/**
 * Adottante con profilo COMPLETO (questionario + consenso GDPR).
 */
function adopterWithCompleteProfile(): User
{
    $adopter = User::factory()->create();
    AdopterProfile::factory()->forUser($adopter)->complete()->create();
    $adopter->load('adopterProfile');

    return $adopter;
}

it('creates a submitted application when the adopter profile is complete', function () {
    $adopter = adopterWithCompleteProfile();
    $animal = Animal::factory()->create();

    $application = app(SubmitApplication::class)->handle($adopter, $animal);

    expect($application->statusEnum())->toBe(ApplicationStatus::Submitted)
        ->and($application->user_id)->toBe($adopter->getKey())
        ->and($application->animal_id)->toBe($animal->getKey())
        ->and($application->organization_id)->toBe($animal->organization_id)
        ->and($application->submitted_at)->not->toBeNull();

    $this->assertDatabaseHas('adoption_applications', [
        'id' => $application->getKey(),
        'status' => ApplicationStatus::Submitted->value,
    ]);
});

it('logs application.submitted caused by the adopter', function () {
    $adopter = adopterWithCompleteProfile();
    $animal = Animal::factory()->create();

    $application = app(SubmitApplication::class)->handle($adopter, $animal);

    $activity = Activity::query()->where('description', 'application.submitted')->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->subject_id)->toBe($application->getKey())
        ->and($activity->causer_id)->toBe($adopter->getKey());
});

it('throws IncompleteAdopterProfile and creates no row when the profile is incomplete', function () {
    // Nessun AdopterProfile del tutto: profilo incompleto.
    $adopter = User::factory()->create();
    $animal = Animal::factory()->create();

    expect(fn () => app(SubmitApplication::class)->handle($adopter, $animal))
        ->toThrow(IncompleteAdopterProfile::class);

    expect(AdoptionApplication::query()->count())->toBe(0);
});

it('throws IncompleteAdopterProfile when the GDPR consent is missing and creates no row', function () {
    $adopter = User::factory()->create();
    AdopterProfile::factory()->forUser($adopter)->withoutConsent()->create();
    $adopter->load('adopterProfile');
    $animal = Animal::factory()->create();

    expect(fn () => app(SubmitApplication::class)->handle($adopter, $animal))
        ->toThrow(IncompleteAdopterProfile::class);

    expect(AdoptionApplication::query()->count())->toBe(0);
});

it('throws DuplicateApplication on a second submit for the same animal', function () {
    $adopter = adopterWithCompleteProfile();
    $animal = Animal::factory()->create();

    app(SubmitApplication::class)->handle($adopter, $animal);

    expect(fn () => app(SubmitApplication::class)->handle($adopter, $animal))
        ->toThrow(DuplicateApplication::class);

    expect(AdoptionApplication::query()->count())->toBe(1);
});

it('notifies the organizations verified members on success and skips unverified ones', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Notification::fake();

    ['organization' => $organization, 'verified' => $verified, 'unverified' => $unverified] = orgWithMixedStaff();
    $animal = Animal::factory()->forOrganization($organization)->create();
    $adopter = adopterWithCompleteProfile();

    app(SubmitApplication::class)->handle($adopter, $animal);

    foreach ($verified as $member) {
        Notification::assertSentTo($member, NewApplicationReceived::class);
    }

    Notification::assertNotSentTo($unverified, NewApplicationReceived::class);
    Notification::assertSentTimes(NewApplicationReceived::class, count($verified));
});

it('sends no notification when the profile is incomplete (no row, no notification)', function () {
    Notification::fake();

    $adopter = User::factory()->create();
    $animal = Animal::factory()->create();

    expect(fn () => app(SubmitApplication::class)->handle($adopter, $animal))
        ->toThrow(IncompleteAdopterProfile::class);

    Notification::assertNothingSent();
});

it('notifies only once across a submit followed by a duplicate submit', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Notification::fake();

    ['organization' => $organization, 'verified' => $verified] = orgWithMixedStaff();
    $animal = Animal::factory()->forOrganization($organization)->create();
    $adopter = adopterWithCompleteProfile();

    app(SubmitApplication::class)->handle($adopter, $animal);

    // Il secondo invio fallisce sulla guardia di unicità: nessuna nuova notifica.
    expect(fn () => app(SubmitApplication::class)->handle($adopter, $animal))
        ->toThrow(DuplicateApplication::class);

    // Solo il primo invio (riuscito) ha notificato i membri verificati.
    Notification::assertSentTimes(NewApplicationReceived::class, count($verified));
});
