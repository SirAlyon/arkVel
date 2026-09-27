<?php

declare(strict_types=1);

use App\Actions\Fostering\SubmitFosterApplication;
use App\Actions\Organizations\AddOrganizationMember;
use App\Enums\FosterApplicationStatus;
use App\Enums\MembershipRole;
use App\Exceptions\DuplicateFosterApplication;
use App\Exceptions\IncompleteAdopterProfile;
use App\Models\AdopterProfile;
use App\Models\Animal;
use App\Models\FosterApplication;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\NewFosterApplicationReceived;
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
function orgWithMixedFosterStaff(): array
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
 * Affidatario con profilo di screening COMPLETO (questionario + consenso GDPR):
 * lo stesso profilo dell'adozione, riusato per l'affido.
 */
function carerWithCompleteProfile(): User
{
    $carer = User::factory()->create();
    AdopterProfile::factory()->forUser($carer)->complete()->create();
    $carer->load('adopterProfile');

    return $carer;
}

it('creates a submitted foster application when the carer profile is complete', function () {
    $carer = carerWithCompleteProfile();
    $animal = Animal::factory()->create();

    $application = app(SubmitFosterApplication::class)->handle($carer, $animal);

    expect($application->statusEnum())->toBe(FosterApplicationStatus::Submitted)
        ->and($application->user_id)->toBe($carer->getKey())
        ->and($application->animal_id)->toBe($animal->getKey())
        ->and($application->organization_id)->toBe($animal->organization_id)
        ->and($application->submitted_at)->not->toBeNull();

    $this->assertDatabaseHas('foster_applications', [
        'id' => $application->getKey(),
        'status' => FosterApplicationStatus::Submitted->value,
    ]);
});

it('logs foster_application.submitted caused by the carer', function () {
    $carer = carerWithCompleteProfile();
    $animal = Animal::factory()->create();

    $application = app(SubmitFosterApplication::class)->handle($carer, $animal);

    $activity = Activity::query()->where('description', 'foster_application.submitted')->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->subject_id)->toBe($application->getKey())
        ->and($activity->causer_id)->toBe($carer->getKey());
});

it('throws IncompleteAdopterProfile and creates no row when the profile is incomplete', function () {
    $carer = User::factory()->create();
    $animal = Animal::factory()->create();

    expect(fn () => app(SubmitFosterApplication::class)->handle($carer, $animal))
        ->toThrow(IncompleteAdopterProfile::class);

    expect(FosterApplication::query()->count())->toBe(0);
});

it('throws DuplicateFosterApplication on a second submit for the same animal', function () {
    $carer = carerWithCompleteProfile();
    $animal = Animal::factory()->create();

    app(SubmitFosterApplication::class)->handle($carer, $animal);

    expect(fn () => app(SubmitFosterApplication::class)->handle($carer, $animal))
        ->toThrow(DuplicateFosterApplication::class);

    expect(FosterApplication::query()->count())->toBe(1);
});

it('notifies the organizations verified members on success and skips unverified ones', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Notification::fake();

    ['organization' => $organization, 'verified' => $verified, 'unverified' => $unverified] = orgWithMixedFosterStaff();
    $animal = Animal::factory()->forOrganization($organization)->create();
    $carer = carerWithCompleteProfile();

    app(SubmitFosterApplication::class)->handle($carer, $animal);

    foreach ($verified as $member) {
        Notification::assertSentTo($member, NewFosterApplicationReceived::class);
    }

    Notification::assertNotSentTo($unverified, NewFosterApplicationReceived::class);
    Notification::assertSentTimes(NewFosterApplicationReceived::class, count($verified));
});

it('sends no notification when the profile is incomplete (no row, no notification)', function () {
    Notification::fake();

    $carer = User::factory()->create();
    $animal = Animal::factory()->create();

    expect(fn () => app(SubmitFosterApplication::class)->handle($carer, $animal))
        ->toThrow(IncompleteAdopterProfile::class);

    Notification::assertNothingSent();
});
