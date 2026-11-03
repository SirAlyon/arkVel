<?php

declare(strict_types=1);

use App\Actions\Applications\WithdrawApplication;
use App\Enums\AnimalStatus;
use App\Enums\ApplicationStatus;
use App\Exceptions\CannotWithdrawApplication;
use App\Exceptions\InvalidApplicationTransition;
use App\Models\AdoptionApplication;
use App\Models\Animal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

/**
 * Ogni stato PRE-TERMINALE dal quale il grafo consente il ritiro.
 *
 * @return array<string, array{string}>
 */
dataset('preTerminalStates', [
    'submitted' => ['submitted'],
    'under review' => ['underReview'],
    'interview' => ['interview'],
    'approved' => ['approved'],
    'adoption pending' => ['adoptionPending'],
    'waitlisted' => ['waitlisted'],
]);

it('withdraws from any pre-terminal state', function (string $factoryState) {
    $adopter = User::factory()->create();
    $application = AdoptionApplication::factory()->{$factoryState}()->forAdopter($adopter)->create();

    $result = app(WithdrawApplication::class)->handle($application, $adopter, 'Non posso più adottare.');

    expect($result->statusEnum())->toBe(ApplicationStatus::Withdrawn)
        ->and($application->fresh()->statusEnum())->toBe(ApplicationStatus::Withdrawn)
        ->and($application->fresh()->decided_at)->not->toBeNull()
        ->and($application->fresh()->decision_reason)->toBe('Non posso più adottare.');
})->with('preTerminalStates');

it('logs application.withdrawn caused by the adopter', function () {
    $adopter = User::factory()->create();
    $application = AdoptionApplication::factory()->submitted()->forAdopter($adopter)->create();

    app(WithdrawApplication::class)->handle($application, $adopter);

    $activity = Activity::query()->where('description', 'application.withdrawn')->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->subject_id)->toBe($application->getKey())
        ->and($activity->causer_id)->toBe($adopter->getKey())
        ->and($activity->properties->get('from'))->toBe(ApplicationStatus::Submitted->value);
});

it('rejects a non-owner and leaves the status untouched', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $application = AdoptionApplication::factory()->submitted()->forAdopter($owner)->create();

    expect(fn () => app(WithdrawApplication::class)->handle($application, $intruder))
        ->toThrow(CannotWithdrawApplication::class);

    expect($application->fresh()->statusEnum())->toBe(ApplicationStatus::Submitted)
        ->and(Activity::query()->where('description', 'application.withdrawn')->count())->toBe(0);
});

it('releases the reserved animal (AdoptionPending -> Available) when withdrawing the holding application', function () {
    $adopter = User::factory()->create();
    // L'animale è RISERVATO (AdoptionPending) da questa candidatura.
    $animal = Animal::factory()->create(['status' => AnimalStatus::AdoptionPending]);
    $application = AdoptionApplication::factory()
        ->forAnimal($animal)
        ->forAdopter($adopter)
        ->adoptionPending()
        ->create();

    app(WithdrawApplication::class)->handle($application, $adopter);

    expect($application->fresh()->statusEnum())->toBe(ApplicationStatus::Withdrawn)
        // Il ritiro rilascia l'animale: torna disponibile.
        ->and($animal->fresh()->status)->toBe(AnimalStatus::Available);

    // Il rilascio è loggato da TransitionAnimalStatus.
    expect(Activity::query()->where('description', 'animal.status_changed')->count())->toBe(1);
});

it('does not touch the animal when withdrawing an application that had not reserved it', function () {
    $adopter = User::factory()->create();
    // Animale Available: la candidatura non lo teneva riservato.
    $animal = Animal::factory()->available()->create();
    $application = AdoptionApplication::factory()
        ->forAnimal($animal)
        ->forAdopter($adopter)
        ->underReview()
        ->create();

    app(WithdrawApplication::class)->handle($application, $adopter);

    expect($animal->fresh()->status)->toBe(AnimalStatus::Available)
        ->and(Activity::query()->where('description', 'animal.status_changed')->count())->toBe(0);
});

it('does not release an animal still reserved by another pending application', function () {
    $adopter = User::factory()->create();
    $animal = Animal::factory()->create(['status' => AnimalStatus::AdoptionPending]);

    // Due candidature in AdoptionPending per lo stesso animale (scenario limite):
    // ritirandone una, l'altra mantiene la riserva.
    $withdrawing = AdoptionApplication::factory()
        ->forAnimal($animal)
        ->forAdopter($adopter)
        ->adoptionPending()
        ->create();
    AdoptionApplication::factory()
        ->forAnimal($animal)
        ->adoptionPending()
        ->create();

    app(WithdrawApplication::class)->handle($withdrawing, $adopter);

    expect($animal->fresh()->status)->toBe(AnimalStatus::AdoptionPending);
});

it('throws InvalidApplicationTransition when withdrawing from a terminal state', function (string $factoryState) {
    $adopter = User::factory()->create();
    $application = AdoptionApplication::factory()->{$factoryState}()->forAdopter($adopter)->create();
    $before = $application->statusEnum();

    expect(fn () => app(WithdrawApplication::class)->handle($application, $adopter))
        ->toThrow(InvalidApplicationTransition::class);

    expect($application->fresh()->statusEnum())->toBe($before)
        ->and(Activity::query()->where('description', 'application.withdrawn')->count())->toBe(0);
})->with([
    'completed' => ['completed'],
    'rejected' => ['rejected'],
    'withdrawn' => ['withdrawn'],
]);
