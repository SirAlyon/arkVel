<?php

declare(strict_types=1);

use App\Actions\Fostering\AdvanceFosterApplication;
use App\Enums\FosterApplicationStatus;
use App\Exceptions\InvalidFosterApplicationTransition;
use App\Models\FosterApplication;
use App\Models\User;
use App\Notifications\FosterApplicationStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->actor = User::factory()->create();
});

it('advances Submitted -> UnderReview persisting, logging and notifying the carer', function () {
    $application = FosterApplication::factory()->submitted()->create();

    $result = app(AdvanceFosterApplication::class)->handle(
        $application,
        FosterApplicationStatus::UnderReview,
        $this->actor,
    );

    expect($result->statusEnum())->toBe(FosterApplicationStatus::UnderReview)
        ->and($application->fresh()->statusEnum())->toBe(FosterApplicationStatus::UnderReview);

    $activity = Activity::query()->where('description', 'foster_application.status_changed')->latest('id')->first();
    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($this->actor->getKey())
        ->and($activity->properties->get('from'))->toBe(FosterApplicationStatus::Submitted->value)
        ->and($activity->properties->get('to'))->toBe(FosterApplicationStatus::UnderReview->value);

    Notification::assertSentTo(
        $application->user,
        FosterApplicationStatusChanged::class,
        function (FosterApplicationStatusChanged $notification): bool {
            return $notification->to === FosterApplicationStatus::UnderReview
                && $notification->from === FosterApplicationStatus::Submitted;
        },
    );
});

it('approves from under review', function () {
    $application = FosterApplication::factory()->underReview()->create();

    app(AdvanceFosterApplication::class)->handle(
        $application,
        FosterApplicationStatus::Approved,
        $this->actor,
    );

    $fresh = $application->fresh();
    expect($fresh->statusEnum())->toBe(FosterApplicationStatus::Approved)
        ->and($fresh->decided_at)->not->toBeNull();
});

it('records decided_at and decision_reason when rejecting from under review', function () {
    $application = FosterApplication::factory()->underReview()->create();

    app(AdvanceFosterApplication::class)->handle(
        $application,
        FosterApplicationStatus::Rejected,
        $this->actor,
        'Abitazione non idonea.',
    );

    $fresh = $application->fresh();
    expect($fresh->statusEnum())->toBe(FosterApplicationStatus::Rejected)
        ->and($fresh->decided_at)->not->toBeNull()
        ->and($fresh->decision_reason)->toBe('Abitazione non idonea.');
});

it('sets decided_at when waitlisting from under review', function () {
    $application = FosterApplication::factory()->underReview()->create();

    app(AdvanceFosterApplication::class)->handle(
        $application,
        FosterApplicationStatus::Waitlisted,
        $this->actor,
        'In attesa di posto.',
    );

    $fresh = $application->fresh();
    expect($fresh->statusEnum())->toBe(FosterApplicationStatus::Waitlisted)
        ->and($fresh->decided_at)->not->toBeNull()
        ->and($fresh->decision_reason)->toBe('In attesa di posto.');
});

it('moves Waitlisted -> UnderReview', function () {
    $application = FosterApplication::factory()->waitlisted()->create();

    app(AdvanceFosterApplication::class)->handle(
        $application,
        FosterApplicationStatus::UnderReview,
        $this->actor,
    );

    expect($application->fresh()->statusEnum())->toBe(FosterApplicationStatus::UnderReview);
});

it('rejects forbidden targets leaving the status untouched with no log or notification', function (string $target) {
    $application = FosterApplication::factory()->approved()->create();

    expect(fn () => app(AdvanceFosterApplication::class)->handle(
        $application,
        FosterApplicationStatus::from($target),
        $this->actor,
    ))->toThrow(InvalidFosterApplicationTransition::class);

    expect($application->fresh()->statusEnum())->toBe(FosterApplicationStatus::Approved)
        ->and(Activity::query()->where('description', 'foster_application.status_changed')->count())->toBe(0);

    Notification::assertNothingSent();
})->with([
    'active' => ['active'],
    'completed' => ['completed'],
    'withdrawn' => ['withdrawn'],
]);

it('throws InvalidFosterApplicationTransition on an illegal graph edge and leaves the status untouched', function () {
    // Submitted -> Approved non è consentito dal grafo.
    $application = FosterApplication::factory()->submitted()->create();

    expect(fn () => app(AdvanceFosterApplication::class)->handle(
        $application,
        FosterApplicationStatus::Approved,
        $this->actor,
    ))->toThrow(InvalidFosterApplicationTransition::class);

    expect($application->fresh()->statusEnum())->toBe(FosterApplicationStatus::Submitted)
        ->and(Activity::query()->where('description', 'foster_application.status_changed')->count())->toBe(0);

    Notification::assertNothingSent();
});

it('carries the from/to states on the InvalidFosterApplicationTransition exception', function () {
    $application = FosterApplication::factory()->submitted()->create();

    try {
        app(AdvanceFosterApplication::class)->handle($application, FosterApplicationStatus::Approved, $this->actor);
        $this->fail('Expected InvalidFosterApplicationTransition was not thrown.');
    } catch (InvalidFosterApplicationTransition $exception) {
        expect($exception->from)->toBe(FosterApplicationStatus::Submitted)
            ->and($exception->to)->toBe(FosterApplicationStatus::Approved);
    }
});
