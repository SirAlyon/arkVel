<?php

declare(strict_types=1);

use App\Actions\Applications\WithdrawApplication;
use App\Enums\ApplicationStatus;
use App\Exceptions\CannotWithdrawApplication;
use App\Exceptions\InvalidApplicationTransition;
use App\Models\AdoptionApplication;
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
