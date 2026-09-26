<?php

declare(strict_types=1);

use App\Actions\Animals\TransitionAnimalStatus;
use App\Enums\AnimalStatus;
use App\Exceptions\InvalidAnimalStatusTransition;
use App\Models\Animal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

it('applies a valid transition and persists the new status', function () {
    $animal = Animal::factory()->draft()->create();

    $result = app(TransitionAnimalStatus::class)->handle($animal, AnimalStatus::Available);

    expect($result->status)->toBe(AnimalStatus::Available)
        ->and($animal->fresh()->status)->toBe(AnimalStatus::Available);
});

it('records an activity log entry for the transition', function () {
    $animal = Animal::factory()->draft()->create();

    app(TransitionAnimalStatus::class)->handle($animal, AnimalStatus::Available);

    $activity = Activity::query()->where('description', 'animal.status_changed')->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->subject_id)->toBe($animal->getKey())
        ->and($activity->properties->get('from'))->toBe(AnimalStatus::Draft->value)
        ->and($activity->properties->get('to'))->toBe(AnimalStatus::Available->value);
});

it('throws on an invalid transition and leaves the status untouched', function () {
    $animal = Animal::factory()->draft()->create();

    expect(fn () => app(TransitionAnimalStatus::class)->handle($animal, AnimalStatus::Adopted))
        ->toThrow(InvalidAnimalStatusTransition::class);

    expect($animal->fresh()->status)->toBe(AnimalStatus::Draft);
});

it('does not log anything when the transition is rejected', function () {
    $animal = Animal::factory()->draft()->create();

    try {
        app(TransitionAnimalStatus::class)->handle($animal, AnimalStatus::Adopted);
    } catch (InvalidAnimalStatusTransition) {
        // atteso
    }

    expect(Activity::query()->where('description', 'animal.status_changed')->count())->toBe(0);
});

it('carries the from/to states on the exception', function () {
    $animal = Animal::factory()->draft()->create();

    try {
        app(TransitionAnimalStatus::class)->handle($animal, AnimalStatus::Adopted);
        $this->fail('Expected InvalidAnimalStatusTransition was not thrown.');
    } catch (InvalidAnimalStatusTransition $exception) {
        expect($exception->from)->toBe(AnimalStatus::Draft)
            ->and($exception->to)->toBe(AnimalStatus::Adopted);
    }
});
