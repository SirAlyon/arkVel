<?php

declare(strict_types=1);

use App\Enums\ApplicationStatus;
use App\Models\AdoptionApplication;
use App\States\Application\AdoptionPending;
use App\States\Application\ApplicationState;
use App\States\Application\Approved;
use App\States\Application\Completed;
use App\States\Application\Interview;
use App\States\Application\Rejected;
use App\States\Application\Submitted;
use App\States\Application\UnderReview;
use App\States\Application\Waitlisted;
use App\States\Application\Withdrawn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\ModelStates\Exceptions\CouldNotPerformTransition;

uses(RefreshDatabase::class);

/**
 * @param  class-string<ApplicationState>  $stateClass
 */
function makeApplicationIn(string $stateClass): AdoptionApplication
{
    return AdoptionApplication::factory()->create(['status' => $stateClass]);
}

// ---------------------------------------------------------------------------
// $name ↔ enum alignment + status()/label()/color() delegation
// ---------------------------------------------------------------------------

it('aligns each concrete state $name with the matching enum value', function (string $stateClass, ApplicationStatus $status) {
    expect($stateClass::$name)->toBe($status->value)
        ->and($stateClass::getMorphClass())->toBe($status->value);
})->with([
    'submitted' => [Submitted::class, ApplicationStatus::Submitted],
    'under_review' => [UnderReview::class, ApplicationStatus::UnderReview],
    'interview' => [Interview::class, ApplicationStatus::Interview],
    'approved' => [Approved::class, ApplicationStatus::Approved],
    'adoption_pending' => [AdoptionPending::class, ApplicationStatus::AdoptionPending],
    'completed' => [Completed::class, ApplicationStatus::Completed],
    'rejected' => [Rejected::class, ApplicationStatus::Rejected],
    'withdrawn' => [Withdrawn::class, ApplicationStatus::Withdrawn],
    'waitlisted' => [Waitlisted::class, ApplicationStatus::Waitlisted],
]);

it('returns the matching enum from status() and delegates label()/color()', function (string $stateClass, ApplicationStatus $status) {
    $state = makeApplicationIn($stateClass)->status;

    expect($state->status())->toBe($status)
        ->and($state->label())->toBe($status->getLabel())
        ->and($state->color())->toBe($status->getColor());
})->with([
    'submitted' => [Submitted::class, ApplicationStatus::Submitted],
    'under_review' => [UnderReview::class, ApplicationStatus::UnderReview],
    'interview' => [Interview::class, ApplicationStatus::Interview],
    'approved' => [Approved::class, ApplicationStatus::Approved],
    'adoption_pending' => [AdoptionPending::class, ApplicationStatus::AdoptionPending],
    'completed' => [Completed::class, ApplicationStatus::Completed],
    'rejected' => [Rejected::class, ApplicationStatus::Rejected],
    'withdrawn' => [Withdrawn::class, ApplicationStatus::Withdrawn],
    'waitlisted' => [Waitlisted::class, ApplicationStatus::Waitlisted],
]);

it('defaults a freshly instantiated application to Submitted', function () {
    // Nessun create/DB: la default state è applicata all'init del model.
    $application = new AdoptionApplication;

    expect($application->status)->toBeInstanceOf(Submitted::class)
        ->and($application->status->status())->toBe(ApplicationStatus::Submitted);
});

it('sets the default state class in the config', function () {
    expect(ApplicationState::config()->defaultStateClass)->toBe(Submitted::class);
});

// ---------------------------------------------------------------------------
// ALLOWED transitions — canTransitionTo() true AND transitionTo() succeeds
// ---------------------------------------------------------------------------

it('allows the transition via canTransitionTo and transitionTo', function (string $fromClass, string $toClass) {
    $application = makeApplicationIn($fromClass);

    expect($application->status->canTransitionTo($toClass))->toBeTrue()
        ->and($application->status->transitionableStates())->toContain($toClass::$name);

    $application->status->transitionTo($toClass);

    expect($application->fresh()->status)->toBeInstanceOf($toClass);
})->with([
    'Submitted → UnderReview' => [Submitted::class, UnderReview::class],

    'UnderReview → Interview' => [UnderReview::class, Interview::class],
    'UnderReview → Rejected' => [UnderReview::class, Rejected::class],
    'UnderReview → Waitlisted' => [UnderReview::class, Waitlisted::class],

    'Interview → Approved' => [Interview::class, Approved::class],
    'Interview → Rejected' => [Interview::class, Rejected::class],

    'Approved → AdoptionPending' => [Approved::class, AdoptionPending::class],

    'AdoptionPending → Completed' => [AdoptionPending::class, Completed::class],

    'Waitlisted → UnderReview' => [Waitlisted::class, UnderReview::class],

    'Submitted → Withdrawn' => [Submitted::class, Withdrawn::class],
    'UnderReview → Withdrawn' => [UnderReview::class, Withdrawn::class],
    'Interview → Withdrawn' => [Interview::class, Withdrawn::class],
    'Approved → Withdrawn' => [Approved::class, Withdrawn::class],
    'AdoptionPending → Withdrawn' => [AdoptionPending::class, Withdrawn::class],
    'Waitlisted → Withdrawn' => [Waitlisted::class, Withdrawn::class],
]);

// ---------------------------------------------------------------------------
// FORBIDDEN transitions — canTransitionTo() false AND transitionTo() throws
// ---------------------------------------------------------------------------

it('forbids the transition via canTransitionTo and transitionTo throws', function (string $fromClass, string $toClass) {
    $application = makeApplicationIn($fromClass);

    expect($application->status->canTransitionTo($toClass))->toBeFalse()
        ->and($application->status->transitionableStates())->not->toContain($toClass::$name);

    expect(fn () => $application->status->transitionTo($toClass))
        ->toThrow(CouldNotPerformTransition::class);

    // Lo stato non è stato toccato dal tentativo fallito.
    expect($application->fresh()->status)->toBeInstanceOf($fromClass);
})->with([
    // Submitted: unica uscita è UnderReview (+ Withdrawn).
    'Submitted → Interview' => [Submitted::class, Interview::class],
    'Submitted → Approved' => [Submitted::class, Approved::class],
    'Submitted → AdoptionPending' => [Submitted::class, AdoptionPending::class],
    'Submitted → Completed' => [Submitted::class, Completed::class],
    'Submitted → Rejected' => [Submitted::class, Rejected::class],
    'Submitted → Waitlisted' => [Submitted::class, Waitlisted::class],

    // UnderReview: NON salta a stati di adozione.
    'UnderReview → Approved' => [UnderReview::class, Approved::class],
    'UnderReview → AdoptionPending' => [UnderReview::class, AdoptionPending::class],
    'UnderReview → Completed' => [UnderReview::class, Completed::class],

    // Interview: solo Approved/Rejected (+ Withdrawn).
    'Interview → AdoptionPending' => [Interview::class, AdoptionPending::class],
    'Interview → Completed' => [Interview::class, Completed::class],
    'Interview → Waitlisted' => [Interview::class, Waitlisted::class],
    'Interview → UnderReview' => [Interview::class, UnderReview::class],

    // Approved: solo AdoptionPending (+ Withdrawn).
    'Approved → Completed' => [Approved::class, Completed::class],
    'Approved → Interview' => [Approved::class, Interview::class],
    'Approved → Rejected' => [Approved::class, Rejected::class],

    // AdoptionPending: solo Completed (+ Withdrawn).
    'AdoptionPending → Approved' => [AdoptionPending::class, Approved::class],
    'AdoptionPending → Rejected' => [AdoptionPending::class, Rejected::class],
    'AdoptionPending → Interview' => [AdoptionPending::class, Interview::class],

    // Waitlisted: solo UnderReview (+ Withdrawn).
    'Waitlisted → Approved' => [Waitlisted::class, Approved::class],
    'Waitlisted → Rejected' => [Waitlisted::class, Rejected::class],
    'Waitlisted → Interview' => [Waitlisted::class, Interview::class],
]);

// ---------------------------------------------------------------------------
// TERMINAL states — no outgoing edges, Withdrawn NOT reachable from them
// ---------------------------------------------------------------------------

it('treats Completed, Rejected and Withdrawn as terminal (no outgoing edges)', function (string $terminalClass) {
    $application = makeApplicationIn($terminalClass);

    expect($application->status->transitionableStates())->toBe([]);

    // Nessuno stato è raggiungibile, incluso Withdrawn.
    foreach ([
        Submitted::class,
        UnderReview::class,
        Interview::class,
        Approved::class,
        AdoptionPending::class,
        Completed::class,
        Rejected::class,
        Withdrawn::class,
        Waitlisted::class,
    ] as $targetClass) {
        expect($application->status->canTransitionTo($targetClass))->toBeFalse();
    }

    expect(fn () => $application->status->transitionTo(Withdrawn::class))
        ->toThrow(CouldNotPerformTransition::class);
})->with([
    'completed' => [Completed::class],
    'rejected' => [Rejected::class],
    'withdrawn' => [Withdrawn::class],
]);

// ---------------------------------------------------------------------------
// transitionableStates() exact sets — the whole graph in one place
// ---------------------------------------------------------------------------

it('exposes exactly the expected transitionable states per state', function (string $fromClass, array $expected) {
    $application = makeApplicationIn($fromClass);

    expect($application->status->transitionableStates())->toEqualCanonicalizing($expected);
})->with([
    'submitted' => [Submitted::class, [UnderReview::$name, Withdrawn::$name]],
    'under_review' => [UnderReview::class, [Interview::$name, Rejected::$name, Waitlisted::$name, Withdrawn::$name]],
    'interview' => [Interview::class, [Approved::$name, Rejected::$name, Withdrawn::$name]],
    'approved' => [Approved::class, [AdoptionPending::$name, Withdrawn::$name]],
    'adoption_pending' => [AdoptionPending::class, [Completed::$name, Withdrawn::$name]],
    'waitlisted' => [Waitlisted::class, [UnderReview::$name, Withdrawn::$name]],
    'completed' => [Completed::class, []],
    'rejected' => [Rejected::class, []],
    'withdrawn' => [Withdrawn::class, []],
]);
