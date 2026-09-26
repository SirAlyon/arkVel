<?php

declare(strict_types=1);

use App\Enums\FosterApplicationStatus;
use App\Models\FosterApplication;
use App\States\Foster\Active;
use App\States\Foster\Approved;
use App\States\Foster\Completed;
use App\States\Foster\FosterApplicationState;
use App\States\Foster\Rejected;
use App\States\Foster\Submitted;
use App\States\Foster\UnderReview;
use App\States\Foster\Waitlisted;
use App\States\Foster\Withdrawn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\ModelStates\Exceptions\CouldNotPerformTransition;

uses(RefreshDatabase::class);

/**
 * @param  class-string<FosterApplicationState>  $stateClass
 */
function makeFosterApplicationIn(string $stateClass): FosterApplication
{
    return FosterApplication::factory()->create(['status' => $stateClass]);
}

// ---------------------------------------------------------------------------
// $name ↔ enum alignment + status()/label()/color() delegation
// ---------------------------------------------------------------------------

it('aligns each concrete state $name with the matching enum value', function (string $stateClass, FosterApplicationStatus $status) {
    expect($stateClass::$name)->toBe($status->value)
        ->and($stateClass::getMorphClass())->toBe($status->value);
})->with([
    'submitted' => [Submitted::class, FosterApplicationStatus::Submitted],
    'under_review' => [UnderReview::class, FosterApplicationStatus::UnderReview],
    'approved' => [Approved::class, FosterApplicationStatus::Approved],
    'rejected' => [Rejected::class, FosterApplicationStatus::Rejected],
    'waitlisted' => [Waitlisted::class, FosterApplicationStatus::Waitlisted],
    'active' => [Active::class, FosterApplicationStatus::Active],
    'completed' => [Completed::class, FosterApplicationStatus::Completed],
    'withdrawn' => [Withdrawn::class, FosterApplicationStatus::Withdrawn],
]);

it('returns the matching enum from status() and delegates label()/color()', function (string $stateClass, FosterApplicationStatus $status) {
    $state = makeFosterApplicationIn($stateClass)->status;

    expect($state->status())->toBe($status)
        ->and($state->label())->toBe($status->getLabel())
        ->and($state->color())->toBe($status->getColor());
})->with([
    'submitted' => [Submitted::class, FosterApplicationStatus::Submitted],
    'under_review' => [UnderReview::class, FosterApplicationStatus::UnderReview],
    'approved' => [Approved::class, FosterApplicationStatus::Approved],
    'rejected' => [Rejected::class, FosterApplicationStatus::Rejected],
    'waitlisted' => [Waitlisted::class, FosterApplicationStatus::Waitlisted],
    'active' => [Active::class, FosterApplicationStatus::Active],
    'completed' => [Completed::class, FosterApplicationStatus::Completed],
    'withdrawn' => [Withdrawn::class, FosterApplicationStatus::Withdrawn],
]);

it('defaults a freshly instantiated foster application to Submitted', function () {
    // Nessun create/DB: la default state è applicata all'init del model.
    $fosterApplication = new FosterApplication;

    expect($fosterApplication->status)->toBeInstanceOf(Submitted::class)
        ->and($fosterApplication->status->status())->toBe(FosterApplicationStatus::Submitted);
});

it('sets the default state class in the config', function () {
    expect(FosterApplicationState::config()->defaultStateClass)->toBe(Submitted::class);
});

// ---------------------------------------------------------------------------
// ALLOWED transitions — canTransitionTo() true AND transitionTo() succeeds
// ---------------------------------------------------------------------------

it('allows the transition via canTransitionTo and transitionTo', function (string $fromClass, string $toClass) {
    $fosterApplication = makeFosterApplicationIn($fromClass);

    expect($fosterApplication->status->canTransitionTo($toClass))->toBeTrue()
        ->and($fosterApplication->status->transitionableStates())->toContain($toClass::$name);

    $fosterApplication->status->transitionTo($toClass);

    expect($fosterApplication->fresh()->status)->toBeInstanceOf($toClass);
})->with([
    'Submitted → UnderReview' => [Submitted::class, UnderReview::class],

    'UnderReview → Approved' => [UnderReview::class, Approved::class],
    'UnderReview → Rejected' => [UnderReview::class, Rejected::class],
    'UnderReview → Waitlisted' => [UnderReview::class, Waitlisted::class],

    'Waitlisted → UnderReview' => [Waitlisted::class, UnderReview::class],

    'Approved → Active' => [Approved::class, Active::class],

    'Active → Completed' => [Active::class, Completed::class],

    'Submitted → Withdrawn' => [Submitted::class, Withdrawn::class],
    'UnderReview → Withdrawn' => [UnderReview::class, Withdrawn::class],
    'Approved → Withdrawn' => [Approved::class, Withdrawn::class],
    'Waitlisted → Withdrawn' => [Waitlisted::class, Withdrawn::class],
    'Active → Withdrawn' => [Active::class, Withdrawn::class],
]);

// ---------------------------------------------------------------------------
// FORBIDDEN transitions — canTransitionTo() false AND transitionTo() throws
// ---------------------------------------------------------------------------

it('forbids the transition via canTransitionTo and transitionTo throws', function (string $fromClass, string $toClass) {
    $fosterApplication = makeFosterApplicationIn($fromClass);

    expect($fosterApplication->status->canTransitionTo($toClass))->toBeFalse()
        ->and($fosterApplication->status->transitionableStates())->not->toContain($toClass::$name);

    expect(fn () => $fosterApplication->status->transitionTo($toClass))
        ->toThrow(CouldNotPerformTransition::class);

    // Lo stato non è stato toccato dal tentativo fallito.
    expect($fosterApplication->fresh()->status)->toBeInstanceOf($fromClass);
})->with([
    // Submitted: unica uscita è UnderReview (+ Withdrawn).
    'Submitted → Approved' => [Submitted::class, Approved::class],
    'Submitted → Active' => [Submitted::class, Active::class],
    'Submitted → Completed' => [Submitted::class, Completed::class],
    'Submitted → Rejected' => [Submitted::class, Rejected::class],
    'Submitted → Waitlisted' => [Submitted::class, Waitlisted::class],

    // UnderReview: NON salta a stati di affido attivo.
    'UnderReview → Active' => [UnderReview::class, Active::class],
    'UnderReview → Completed' => [UnderReview::class, Completed::class],

    // Approved: solo Active (+ Withdrawn).
    'Approved → Completed' => [Approved::class, Completed::class],
    'Approved → UnderReview' => [Approved::class, UnderReview::class],
    'Approved → Rejected' => [Approved::class, Rejected::class],

    // Active: solo Completed (+ Withdrawn).
    'Active → UnderReview' => [Active::class, UnderReview::class],
    'Active → Approved' => [Active::class, Approved::class],
    'Active → Rejected' => [Active::class, Rejected::class],

    // Waitlisted: solo UnderReview (+ Withdrawn).
    'Waitlisted → Approved' => [Waitlisted::class, Approved::class],
    'Waitlisted → Rejected' => [Waitlisted::class, Rejected::class],
    'Waitlisted → Active' => [Waitlisted::class, Active::class],
]);

// ---------------------------------------------------------------------------
// TERMINAL states — no outgoing edges, Withdrawn NOT reachable from them
// ---------------------------------------------------------------------------

it('treats Rejected, Completed and Withdrawn as terminal (no outgoing edges)', function (string $terminalClass) {
    $fosterApplication = makeFosterApplicationIn($terminalClass);

    expect($fosterApplication->status->transitionableStates())->toBe([]);

    // Nessuno stato è raggiungibile, incluso Withdrawn.
    foreach ([
        Submitted::class,
        UnderReview::class,
        Approved::class,
        Rejected::class,
        Waitlisted::class,
        Active::class,
        Completed::class,
        Withdrawn::class,
    ] as $targetClass) {
        expect($fosterApplication->status->canTransitionTo($targetClass))->toBeFalse();
    }

    expect(fn () => $fosterApplication->status->transitionTo(Withdrawn::class))
        ->toThrow(CouldNotPerformTransition::class);
})->with([
    'rejected' => [Rejected::class],
    'completed' => [Completed::class],
    'withdrawn' => [Withdrawn::class],
]);

// ---------------------------------------------------------------------------
// transitionableStates() exact sets — the whole graph in one place
// ---------------------------------------------------------------------------

it('exposes exactly the expected transitionable states per state', function (string $fromClass, array $expected) {
    $fosterApplication = makeFosterApplicationIn($fromClass);

    expect($fosterApplication->status->transitionableStates())->toEqualCanonicalizing($expected);
})->with([
    'submitted' => [Submitted::class, [UnderReview::$name, Withdrawn::$name]],
    'under_review' => [UnderReview::class, [Approved::$name, Rejected::$name, Waitlisted::$name, Withdrawn::$name]],
    'approved' => [Approved::class, [Active::$name, Withdrawn::$name]],
    'active' => [Active::class, [Completed::$name, Withdrawn::$name]],
    'waitlisted' => [Waitlisted::class, [UnderReview::$name, Withdrawn::$name]],
    'rejected' => [Rejected::class, []],
    'completed' => [Completed::class, []],
    'withdrawn' => [Withdrawn::class, []],
]);
