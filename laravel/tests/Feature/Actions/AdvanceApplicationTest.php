<?php

declare(strict_types=1);

use App\Actions\Applications\AdvanceApplication;
use App\Enums\AnimalStatus;
use App\Enums\ApplicationStatus;
use App\Exceptions\AnimalAlreadyHasPendingAdoption;
use App\Exceptions\InvalidAnimalStatusTransition;
use App\Exceptions\InvalidApplicationTransition;
use App\Models\AdoptionApplication;
use App\Models\Animal;
use App\Models\User;
use App\Notifications\ApplicationStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->actor = User::factory()->create();
});

it('advances Submitted -> UnderReview persisting, logging and notifying the adopter', function () {
    $application = AdoptionApplication::factory()->submitted()->create();

    $result = app(AdvanceApplication::class)->handle(
        $application,
        ApplicationStatus::UnderReview,
        $this->actor,
    );

    expect($result->statusEnum())->toBe(ApplicationStatus::UnderReview)
        ->and($application->fresh()->statusEnum())->toBe(ApplicationStatus::UnderReview);

    $activity = Activity::query()->where('description', 'application.status_changed')->latest('id')->first();
    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($this->actor->getKey())
        ->and($activity->properties->get('from'))->toBe(ApplicationStatus::Submitted->value)
        ->and($activity->properties->get('to'))->toBe(ApplicationStatus::UnderReview->value);

    Notification::assertSentTo(
        $application->user,
        ApplicationStatusChanged::class,
        function (ApplicationStatusChanged $notification): bool {
            return $notification->to === ApplicationStatus::UnderReview
                && $notification->from === ApplicationStatus::Submitted;
        },
    );
});

it('walks the full happy path Submitted -> UnderReview -> Interview -> Approved -> AdoptionPending and reserves the animal', function () {
    // L'animale parte Available: entrando in AdoptionPending deve essere riservato.
    $animal = Animal::factory()->available()->create();
    $application = AdoptionApplication::factory()->forAnimal($animal)->submitted()->create();

    foreach ([
        ApplicationStatus::UnderReview,
        ApplicationStatus::Interview,
        ApplicationStatus::Approved,
        ApplicationStatus::AdoptionPending,
    ] as $to) {
        app(AdvanceApplication::class)->handle($application, $to, $this->actor);
    }

    expect($application->fresh()->statusEnum())->toBe(ApplicationStatus::AdoptionPending)
        // Accoppiamento animale: la riserva porta l'animale in AdoptionPending.
        ->and($animal->fresh()->status)->toBe(AnimalStatus::AdoptionPending);

    // La riserva dell'animale è loggata da TransitionAnimalStatus.
    expect(Activity::query()->where('description', 'animal.status_changed')->count())->toBe(1);

    // Una notifica all'adottante per ciascuna delle 4 transizioni.
    Notification::assertSentToTimes($application->user, ApplicationStatusChanged::class, 4);
});

it('records decided_at and decision_reason on decision states', function () {
    $application = AdoptionApplication::factory()->interview()->create();

    app(AdvanceApplication::class)->handle(
        $application,
        ApplicationStatus::Rejected,
        $this->actor,
        'Abitazione non idonea.',
    );

    $fresh = $application->fresh();
    expect($fresh->statusEnum())->toBe(ApplicationStatus::Rejected)
        ->and($fresh->decided_at)->not->toBeNull()
        ->and($fresh->decision_reason)->toBe('Abitazione non idonea.');
});

it('sets decided_at when waitlisting from under review', function () {
    $application = AdoptionApplication::factory()->underReview()->create();

    app(AdvanceApplication::class)->handle(
        $application,
        ApplicationStatus::Waitlisted,
        $this->actor,
        'In attesa di posto.',
    );

    $fresh = $application->fresh();
    expect($fresh->statusEnum())->toBe(ApplicationStatus::Waitlisted)
        ->and($fresh->decided_at)->not->toBeNull()
        ->and($fresh->decision_reason)->toBe('In attesa di posto.');
});

it('throws InvalidApplicationTransition on an illegal edge and leaves the status untouched with no log or notification', function () {
    // Submitted -> Approved non è consentito dal grafo.
    $application = AdoptionApplication::factory()->submitted()->create();

    expect(fn () => app(AdvanceApplication::class)->handle(
        $application,
        ApplicationStatus::Approved,
        $this->actor,
    ))->toThrow(InvalidApplicationTransition::class);

    expect($application->fresh()->statusEnum())->toBe(ApplicationStatus::Submitted)
        ->and(Activity::query()->where('description', 'application.status_changed')->count())->toBe(0);

    Notification::assertNothingSent();
});

it('rejects Completed as a target (finalization belongs to slice 10)', function () {
    $application = AdoptionApplication::factory()->adoptionPending()->create();

    expect(fn () => app(AdvanceApplication::class)->handle(
        $application,
        ApplicationStatus::Completed,
        $this->actor,
    ))->toThrow(InvalidApplicationTransition::class);

    expect($application->fresh()->statusEnum())->toBe(ApplicationStatus::AdoptionPending);
    Notification::assertNothingSent();
});

it('rejects Withdrawn as a target (adopter-only action)', function () {
    $application = AdoptionApplication::factory()->submitted()->create();

    expect(fn () => app(AdvanceApplication::class)->handle(
        $application,
        ApplicationStatus::Withdrawn,
        $this->actor,
    ))->toThrow(InvalidApplicationTransition::class);

    expect($application->fresh()->statusEnum())->toBe(ApplicationStatus::Submitted);
    Notification::assertNothingSent();
});

it('carries the from/to states on the InvalidApplicationTransition exception', function () {
    $application = AdoptionApplication::factory()->submitted()->create();

    try {
        app(AdvanceApplication::class)->handle($application, ApplicationStatus::Approved, $this->actor);
        $this->fail('Expected InvalidApplicationTransition was not thrown.');
    } catch (InvalidApplicationTransition $exception) {
        expect($exception->from)->toBe(ApplicationStatus::Submitted)
            ->and($exception->to)->toBe(ApplicationStatus::Approved);
    }
});

it('throws AnimalAlreadyHasPendingAdoption when a second application for the same animal reaches AdoptionPending', function () {
    $animal = Animal::factory()->create();

    // Prima candidatura già in adozione per lo stesso animale.
    $pending = AdoptionApplication::factory()->forAnimal($animal)->adoptionPending()->create();

    // Seconda candidatura, approvata, che proverebbe a entrare in AdoptionPending.
    $second = AdoptionApplication::factory()->forAnimal($animal)->approved()->create();

    try {
        app(AdvanceApplication::class)->handle($second, ApplicationStatus::AdoptionPending, $this->actor);
        $this->fail('Expected AnimalAlreadyHasPendingAdoption was not thrown.');
    } catch (AnimalAlreadyHasPendingAdoption $exception) {
        expect($exception->animal->getKey())->toBe($animal->getKey())
            ->and($exception->conflictingApplicationId)->toBe($pending->getKey());
    }

    // Lo stato della seconda candidatura resta invariato e non parte alcuna notifica.
    expect($second->fresh()->statusEnum())->toBe(ApplicationStatus::Approved);
    Notification::assertNothingSent();
});

it('allows AdoptionPending when no other application for the same animal is pending or completed', function () {
    $animal = Animal::factory()->available()->create();
    $application = AdoptionApplication::factory()->forAnimal($animal)->approved()->create();

    app(AdvanceApplication::class)->handle($application, ApplicationStatus::AdoptionPending, $this->actor);

    expect($application->fresh()->statusEnum())->toBe(ApplicationStatus::AdoptionPending)
        ->and($animal->fresh()->status)->toBe(AnimalStatus::AdoptionPending);
});

it('reserves an animal that is Reserved when reaching AdoptionPending', function () {
    // Reserved → AdoptionPending è ammesso dal grafo dell'animale.
    $animal = Animal::factory()->create(['status' => AnimalStatus::Reserved]);
    $application = AdoptionApplication::factory()->forAnimal($animal)->approved()->create();

    app(AdvanceApplication::class)->handle($application, ApplicationStatus::AdoptionPending, $this->actor);

    expect($animal->fresh()->status)->toBe(AnimalStatus::AdoptionPending);
});

it('rolls back the application transition when the animal cannot be reserved', function () {
    // Un animale Adopted è terminale: non è riservabile. La riserva fallisce e
    // fa rollback dell'intero avanzamento (candidatura invariata, niente log/notifica).
    $animal = Animal::factory()->adopted()->create();
    $application = AdoptionApplication::factory()->forAnimal($animal)->approved()->create();

    expect(fn () => app(AdvanceApplication::class)->handle(
        $application,
        ApplicationStatus::AdoptionPending,
        $this->actor,
    ))->toThrow(InvalidAnimalStatusTransition::class);

    expect($application->fresh()->statusEnum())->toBe(ApplicationStatus::Approved)
        ->and($animal->fresh()->status)->toBe(AnimalStatus::Adopted)
        ->and(Activity::query()->where('description', 'application.status_changed')->count())->toBe(0);

    Notification::assertNothingSent();
});
