<?php

declare(strict_types=1);

use App\Enums\AnimalStatus;

it('allows the expected transitions from each state', function (AnimalStatus $from, array $expected) {
    expect($from->allowedTransitions())->toEqualCanonicalizing($expected);
})->with([
    'draft' => [AnimalStatus::Draft, [AnimalStatus::Available, AnimalStatus::NotAvailable]],
    'available' => [AnimalStatus::Available, [
        AnimalStatus::Reserved,
        AnimalStatus::AdoptionPending,
        AnimalStatus::MedicalHold,
        AnimalStatus::Fostered,
        AnimalStatus::NotAvailable,
    ]],
    'reserved' => [AnimalStatus::Reserved, [
        AnimalStatus::Available,
        AnimalStatus::AdoptionPending,
        AnimalStatus::NotAvailable,
    ]],
    'adoption_pending' => [AnimalStatus::AdoptionPending, [
        AnimalStatus::Adopted,
        AnimalStatus::Available,
        AnimalStatus::NotAvailable,
    ]],
    'medical_hold' => [AnimalStatus::MedicalHold, [AnimalStatus::Available, AnimalStatus::NotAvailable]],
    'fostered' => [AnimalStatus::Fostered, [
        AnimalStatus::Available,
        AnimalStatus::Adopted,
        AnimalStatus::NotAvailable,
    ]],
    'not_available' => [AnimalStatus::NotAvailable, [AnimalStatus::Available]],
    'adopted (terminal)' => [AnimalStatus::Adopted, []],
]);

it('treats adopted as a terminal state', function () {
    expect(AnimalStatus::Adopted->allowedTransitions())->toBe([]);

    foreach (AnimalStatus::cases() as $target) {
        expect(AnimalStatus::Adopted->canTransitionTo($target))->toBeFalse();
    }
});

it('confirms allowed transitions via canTransitionTo', function () {
    expect(AnimalStatus::Draft->canTransitionTo(AnimalStatus::Available))->toBeTrue()
        ->and(AnimalStatus::Available->canTransitionTo(AnimalStatus::AdoptionPending))->toBeTrue()
        ->and(AnimalStatus::AdoptionPending->canTransitionTo(AnimalStatus::Adopted))->toBeTrue()
        ->and(AnimalStatus::Fostered->canTransitionTo(AnimalStatus::Adopted))->toBeTrue()
        ->and(AnimalStatus::NotAvailable->canTransitionTo(AnimalStatus::Available))->toBeTrue();
});

it('rejects forbidden transitions via canTransitionTo', function () {
    expect(AnimalStatus::Draft->canTransitionTo(AnimalStatus::Adopted))->toBeFalse()
        ->and(AnimalStatus::Draft->canTransitionTo(AnimalStatus::Reserved))->toBeFalse()
        ->and(AnimalStatus::MedicalHold->canTransitionTo(AnimalStatus::Adopted))->toBeFalse()
        ->and(AnimalStatus::Reserved->canTransitionTo(AnimalStatus::Adopted))->toBeFalse()
        ->and(AnimalStatus::Available->canTransitionTo(AnimalStatus::Adopted))->toBeFalse()
        // `Draft` e' pre-pubblicazione: nessun ritorno a bozza da NotAvailable.
        ->and(AnimalStatus::NotAvailable->canTransitionTo(AnimalStatus::Draft))->toBeFalse();
});
