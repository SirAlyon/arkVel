<?php

declare(strict_types=1);

use App\Actions\Fostering\StartFosterPlacement;
use App\Enums\AnimalStatus;
use App\Enums\FosterApplicationStatus;
use App\Enums\FosterPlacementStatus;
use App\Enums\FosterPlacementType;
use App\Exceptions\AnimalAlreadyInFoster;
use App\Exceptions\InvalidAnimalStatusTransition;
use App\Exceptions\InvalidFosterApplicationTransition;
use App\Models\Animal;
use App\Models\FosterApplication;
use App\Models\FosterPlacement;
use App\Models\User;
use App\Notifications\FosterPlacementStarted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->actor = User::factory()->create();
});

/**
 * Richiesta APPROVATA con animale DISPONIBILE (collocabile in affido): è il
 * presupposto dell'avvio di un affido.
 */
function approvedFosterApplicationReadyToStart(): FosterApplication
{
    $animal = Animal::factory()->available()->create();

    return FosterApplication::factory()->forAnimal($animal)->approved()->create();
}

it('starts the placement from an approved application: application Active, placement created, animal Fostered', function () {
    $application = approvedFosterApplicationReadyToStart();
    $animal = $application->animal;

    $placement = app(StartFosterPlacement::class)->handle(
        $application,
        $this->actor,
        FosterPlacementType::PreAdoption,
    );

    // Richiesta ora Active.
    expect($application->fresh()->statusEnum())->toBe(FosterApplicationStatus::Active);

    // Affido creato con i campi corretti.
    expect($placement->animal_id)->toBe($animal->getKey())
        ->and($placement->user_id)->toBe($application->user_id)
        ->and($placement->organization_id)->toBe($application->organization_id)
        ->and($placement->foster_application_id)->toBe($application->getKey())
        ->and($placement->type)->toBe(FosterPlacementType::PreAdoption)
        ->and($placement->status)->toBe(FosterPlacementStatus::Active)
        ->and($placement->start_date)->not->toBeNull()
        ->and($placement->end_date)->toBeNull();

    // Animale collocato in affido + log della transizione animale.
    expect($animal->fresh()->status)->toBe(AnimalStatus::Fostered);
    expect(Activity::query()->where('description', 'animal.status_changed')->count())->toBe(1);

    // Log dell'avvio affido, causato dall'attore, sul placement.
    $activity = Activity::query()->where('description', 'foster.placement_started')->latest('id')->first();
    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($this->actor->getKey())
        ->and($activity->subject_id)->toBe($placement->getKey());

    // Affidatario notificato.
    Notification::assertSentTo(
        $application->user,
        FosterPlacementStarted::class,
        fn (FosterPlacementStarted $notification): bool => $notification->placement->is($placement),
    );
});

it('honours the provided type, start and end dates and notes', function () {
    $application = approvedFosterApplicationReadyToStart();
    $start = now()->addDay()->startOfDay();
    $end = now()->addDays(30)->startOfDay();

    $placement = app(StartFosterPlacement::class)->handle(
        $application,
        $this->actor,
        FosterPlacementType::Medical,
        $start,
        $end,
        'Cure post-operatorie.',
    );

    expect($placement->type)->toBe(FosterPlacementType::Medical)
        ->and($placement->start_date->toDateString())->toBe($start->toDateString())
        ->and($placement->end_date?->toDateString())->toBe($end->toDateString())
        ->and($placement->notes)->toBe('Cure post-operatorie.');
});

it('throws InvalidFosterApplicationTransition when the application is not Approved and creates nothing', function () {
    $animal = Animal::factory()->available()->create();
    $application = FosterApplication::factory()->forAnimal($animal)->underReview()->create();

    expect(fn () => app(StartFosterPlacement::class)->handle(
        $application,
        $this->actor,
        FosterPlacementType::PreAdoption,
    ))->toThrow(InvalidFosterApplicationTransition::class);

    expect(FosterPlacement::query()->count())->toBe(0)
        ->and($application->fresh()->statusEnum())->toBe(FosterApplicationStatus::UnderReview)
        ->and($animal->fresh()->status)->toBe(AnimalStatus::Available);

    Notification::assertNothingSent();
});

it('throws AnimalAlreadyInFoster when a second active placement for the same animal is opened', function () {
    $animal = Animal::factory()->available()->create();

    // Un affido attivo già esistente per questo animale.
    $existing = FosterPlacement::factory()->forAnimal($animal)->active()->create();

    // Seconda richiesta approvata che proverebbe ad aprire un altro affido.
    $second = FosterApplication::factory()->forAnimal($animal)->approved()->create();

    try {
        app(StartFosterPlacement::class)->handle($second, $this->actor, FosterPlacementType::Emergency);
        $this->fail('Expected AnimalAlreadyInFoster was not thrown.');
    } catch (AnimalAlreadyInFoster $exception) {
        expect($exception->animal->getKey())->toBe($animal->getKey())
            ->and($exception->conflictingPlacementId)->toBe($existing->getKey());
    }

    // Lo stato della seconda richiesta resta invariato e non nasce un secondo affido.
    expect($second->fresh()->statusEnum())->toBe(FosterApplicationStatus::Approved)
        ->and(FosterPlacement::query()->count())->toBe(1);

    Notification::assertNothingSent();
});

it('rolls back everything when the animal is not in a placeable state', function () {
    // Un animale Adopted è terminale: non è collocabile. La transizione fallisce
    // e fa rollback dell'intero avvio (richiesta invariata, nessun affido).
    $animal = Animal::factory()->adopted()->create();
    $application = FosterApplication::factory()->forAnimal($animal)->approved()->create();

    expect(fn () => app(StartFosterPlacement::class)->handle(
        $application,
        $this->actor,
        FosterPlacementType::PreAdoption,
    ))->toThrow(InvalidAnimalStatusTransition::class);

    expect($application->fresh()->statusEnum())->toBe(FosterApplicationStatus::Approved)
        ->and($animal->fresh()->status)->toBe(AnimalStatus::Adopted)
        ->and(FosterPlacement::query()->count())->toBe(0);

    Notification::assertNothingSent();
});
