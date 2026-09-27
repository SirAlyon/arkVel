<?php

declare(strict_types=1);

use App\Actions\Fostering\EndFosterPlacement;
use App\Enums\AnimalStatus;
use App\Enums\FosterApplicationStatus;
use App\Enums\FosterPlacementStatus;
use App\Exceptions\CannotEndFosterPlacement;
use App\Models\Animal;
use App\Models\FosterApplication;
use App\Models\FosterPlacement;
use App\Models\User;
use App\Notifications\FosterPlacementEnded;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    $this->actor = User::factory()->create();
});

/**
 * Affido ATTIVO nato da una richiesta Active, con animale In affido: è lo stato
 * di partenza legittimo per la chiusura.
 *
 * @return array{placement: FosterPlacement, application: FosterApplication, animal: Animal}
 */
function activePlacementReadyToEnd(): array
{
    $animal = Animal::factory()->create(['status' => AnimalStatus::Fostered]);
    $application = FosterApplication::factory()->forAnimal($animal)->active()->create();
    $placement = FosterPlacement::factory()->forApplication($application)->active()->create();

    return [
        'placement' => $placement,
        'application' => $application,
        'animal' => $animal,
    ];
}

it('ends the placement, completes the application and returns the animal Fostered -> Available', function () {
    ['placement' => $placement, 'application' => $application, 'animal' => $animal] = activePlacementReadyToEnd();

    $result = app(EndFosterPlacement::class)->handle($placement, $this->actor);

    // Affido concluso.
    expect($result->status)->toBe(FosterPlacementStatus::Ended)
        ->and($placement->fresh()->status)->toBe(FosterPlacementStatus::Ended)
        ->and($placement->fresh()->end_date)->not->toBeNull();

    // Richiesta completata.
    expect($application->fresh()->statusEnum())->toBe(FosterApplicationStatus::Completed)
        ->and($application->fresh()->decided_at)->not->toBeNull();

    // Animale rientrato + log della transizione animale.
    expect($animal->fresh()->status)->toBe(AnimalStatus::Available);
    expect(Activity::query()->where('description', 'animal.status_changed')->count())->toBe(1);

    // Log della chiusura affido, causato dall'attore.
    $activity = Activity::query()->where('description', 'foster.placement_ended')->latest('id')->first();
    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($this->actor->getKey())
        ->and($activity->subject_id)->toBe($placement->getKey());

    // Affidatario notificato.
    Notification::assertSentTo(
        $placement->user,
        FosterPlacementEnded::class,
        fn (FosterPlacementEnded $notification): bool => $notification->placement->is($placement),
    );
});

it('honours the provided end date and replaces the notes', function () {
    ['placement' => $placement] = activePlacementReadyToEnd();
    $end = now()->subDay()->startOfDay();

    app(EndFosterPlacement::class)->handle($placement, $this->actor, $end, 'Animale rientrato in salute.');

    $fresh = $placement->fresh();
    expect($fresh->end_date?->toDateString())->toBe($end->toDateString())
        ->and($fresh->notes)->toBe('Animale rientrato in salute.');
});

it('ends the placement without touching an animal that has already left foster care', function () {
    // Scenario foster-to-adopt (o comunque animale uscito dall'affido per altra
    // via): l'affido è ancora Active ma l'animale non è più Fostered. La chiusura
    // deve comunque concludersi SENZA forzare una transizione animale illegale.
    $animal = Animal::factory()->create(['status' => AnimalStatus::Adopted]);
    $application = FosterApplication::factory()->forAnimal($animal)->active()->create();
    $placement = FosterPlacement::factory()->forApplication($application)->active()->create();

    $result = app(EndFosterPlacement::class)->handle($placement, $this->actor);

    // Affido concluso e richiesta completata, come nel caso normale.
    expect($result->status)->toBe(FosterPlacementStatus::Ended)
        ->and($application->fresh()->statusEnum())->toBe(FosterApplicationStatus::Completed);

    // L'animale NON viene toccato: resta Adopted e non c'è alcun log di transizione.
    expect($animal->fresh()->status)->toBe(AnimalStatus::Adopted);
    expect(Activity::query()->where('description', 'animal.status_changed')->count())->toBe(0);

    // La chiusura è comunque loggata e l'affidatario notificato.
    expect(Activity::query()->where('description', 'foster.placement_ended')->count())->toBe(1);
    Notification::assertSentTo($placement->user, FosterPlacementEnded::class);
});

it('throws CannotEndFosterPlacement when the placement is not active', function () {
    $placement = FosterPlacement::factory()->ended()->create();

    expect(fn () => app(EndFosterPlacement::class)->handle($placement, $this->actor))
        ->toThrow(CannotEndFosterPlacement::class);

    expect(Activity::query()->where('description', 'foster.placement_ended')->count())->toBe(0);
    Notification::assertNothingSent();
});
