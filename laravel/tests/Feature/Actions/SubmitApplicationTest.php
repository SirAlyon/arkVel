<?php

declare(strict_types=1);

use App\Actions\Applications\SubmitApplication;
use App\Enums\ApplicationStatus;
use App\Exceptions\DuplicateApplication;
use App\Exceptions\IncompleteAdopterProfile;
use App\Models\AdopterProfile;
use App\Models\AdoptionApplication;
use App\Models\Animal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

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
