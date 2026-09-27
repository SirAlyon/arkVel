<?php

declare(strict_types=1);

use App\Actions\Fostering\WithdrawFosterApplication;
use App\Enums\AnimalStatus;
use App\Enums\FosterApplicationStatus;
use App\Enums\FosterPlacementStatus;
use App\Exceptions\CannotWithdrawFosterApplication;
use App\Exceptions\InvalidFosterApplicationTransition;
use App\Models\Animal;
use App\Models\FosterApplication;
use App\Models\FosterPlacement;
use App\Models\User;
use App\Notifications\FosterApplicationWithdrawn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

/**
 * Ogni stato PRE-TERMINALE dal quale il grafo consente il ritiro (senza affido
 * attivo: Active è coperto da un test dedicato che crea anche il placement).
 *
 * @return array<string, array{string}>
 */
dataset('preTerminalFosterStates', [
    'submitted' => ['submitted'],
    'under review' => ['underReview'],
    'approved' => ['approved'],
    'waitlisted' => ['waitlisted'],
]);

it('withdraws from any pre-terminal state', function (string $factoryState) {
    $carer = User::factory()->create();
    $application = FosterApplication::factory()->{$factoryState}()->forAdopter($carer)->create();

    $result = app(WithdrawFosterApplication::class)->handle($application, $carer, 'Non posso più accogliere.');

    expect($result->statusEnum())->toBe(FosterApplicationStatus::Withdrawn)
        ->and($application->fresh()->statusEnum())->toBe(FosterApplicationStatus::Withdrawn)
        ->and($application->fresh()->decided_at)->not->toBeNull()
        ->and($application->fresh()->decision_reason)->toBe('Non posso più accogliere.');
})->with('preTerminalFosterStates');

it('logs foster_application.withdrawn caused by the carer and notifies the assigned operator', function () {
    $carer = User::factory()->create();
    // Operatore in carico: destinatario diretto della notifica di ritiro
    // (recipients() preferisce assignedTo quando presente).
    $operator = User::factory()->create();
    $application = FosterApplication::factory()
        ->submitted()
        ->forAdopter($carer)
        ->create(['assigned_to' => $operator->getKey()]);

    Notification::fake();

    app(WithdrawFosterApplication::class)->handle($application, $carer);

    $activity = Activity::query()->where('description', 'foster_application.withdrawn')->latest('id')->first();

    expect($activity)->not->toBeNull()
        ->and($activity->subject_id)->toBe($application->getKey())
        ->and($activity->causer_id)->toBe($carer->getKey())
        ->and($activity->properties->get('from'))->toBe(FosterApplicationStatus::Submitted->value);

    Notification::assertSentTo($operator, FosterApplicationWithdrawn::class);
    Notification::assertSentTimes(FosterApplicationWithdrawn::class, 1);
});

it('rejects a non-owner and leaves the status untouched', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $application = FosterApplication::factory()->submitted()->forAdopter($owner)->create();

    expect(fn () => app(WithdrawFosterApplication::class)->handle($application, $intruder))
        ->toThrow(CannotWithdrawFosterApplication::class);

    expect($application->fresh()->statusEnum())->toBe(FosterApplicationStatus::Submitted)
        ->and(Activity::query()->where('description', 'foster_application.withdrawn')->count())->toBe(0);
});

it('throws InvalidFosterApplicationTransition when withdrawing from a terminal state', function (string $factoryState) {
    $carer = User::factory()->create();
    $application = FosterApplication::factory()->{$factoryState}()->forAdopter($carer)->create();
    $before = $application->statusEnum();

    expect(fn () => app(WithdrawFosterApplication::class)->handle($application, $carer))
        ->toThrow(InvalidFosterApplicationTransition::class);

    expect($application->fresh()->statusEnum())->toBe($before)
        ->and(Activity::query()->where('description', 'foster_application.withdrawn')->count())->toBe(0);
})->with([
    'completed' => ['completed'],
    'rejected' => ['rejected'],
    'withdrawn' => ['withdrawn'],
]);

it('ends the active placement and returns the animal Fostered -> Available when withdrawing from Active', function () {
    $carer = User::factory()->create();
    // Animale In affido, richiesta Active, affido attivo legato alla richiesta.
    $animal = Animal::factory()->create(['status' => AnimalStatus::Fostered]);
    $application = FosterApplication::factory()
        ->forAnimal($animal)
        ->forAdopter($carer)
        ->active()
        ->create();
    $placement = FosterPlacement::factory()
        ->forApplication($application)
        ->active()
        ->create();

    app(WithdrawFosterApplication::class)->handle($application, $carer);

    expect($application->fresh()->statusEnum())->toBe(FosterApplicationStatus::Withdrawn)
        // L'affido è chiuso.
        ->and($placement->fresh()->status)->toBe(FosterPlacementStatus::Ended)
        ->and($placement->fresh()->end_date)->not->toBeNull()
        // L'animale rientra: torna disponibile.
        ->and($animal->fresh()->status)->toBe(AnimalStatus::Available);

    // Il rientro è loggato da TransitionAnimalStatus.
    expect(Activity::query()->where('description', 'animal.status_changed')->count())->toBe(1);
});

it('does not release an animal fostered by another application', function () {
    $carer = User::factory()->create();
    // Animale In affido, ma tenuto da un'ALTRA richiesta/affido attivo.
    $animal = Animal::factory()->create(['status' => AnimalStatus::Fostered]);

    // Questa richiesta è Active ma il suo affido è già stato chiuso (Ended):
    // ritirarla non deve toccare l'animale, che resta tenuto dall'altro affido.
    $withdrawing = FosterApplication::factory()
        ->forAnimal($animal)
        ->forAdopter($carer)
        ->active()
        ->create();
    FosterPlacement::factory()
        ->forApplication($withdrawing)
        ->ended()
        ->create();

    // Un secondo affido attivo per lo stesso animale (di un altro affidatario).
    FosterPlacement::factory()
        ->forAnimal($animal)
        ->active()
        ->create();

    app(WithdrawFosterApplication::class)->handle($withdrawing, $carer);

    expect($withdrawing->fresh()->statusEnum())->toBe(FosterApplicationStatus::Withdrawn)
        // L'animale NON viene rilasciato: nessuna transizione animale.
        ->and($animal->fresh()->status)->toBe(AnimalStatus::Fostered)
        ->and(Activity::query()->where('description', 'animal.status_changed')->count())->toBe(0);
});
