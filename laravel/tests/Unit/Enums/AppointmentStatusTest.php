<?php

declare(strict_types=1);

use App\Enums\AppointmentStatus;

it('is backed by the expected string values', function () {
    expect(AppointmentStatus::Requested->value)->toBe('requested')
        ->and(AppointmentStatus::Confirmed->value)->toBe('confirmed')
        ->and(AppointmentStatus::Completed->value)->toBe('completed')
        ->and(AppointmentStatus::Cancelled->value)->toBe('cancelled')
        ->and(AppointmentStatus::NoShow->value)->toBe('no_show');
});

it('defines exactly five statuses', function () {
    expect(AppointmentStatus::cases())->toHaveCount(5);
});

it('pins the italian labels', function () {
    expect(AppointmentStatus::Requested->getLabel())->toBe('Richiesto')
        ->and(AppointmentStatus::Confirmed->getLabel())->toBe('Confermato')
        ->and(AppointmentStatus::Completed->getLabel())->toBe('Completato')
        ->and(AppointmentStatus::Cancelled->getLabel())->toBe('Annullato')
        ->and(AppointmentStatus::NoShow->getLabel())->toBe('Assente');
});

it('pins the colors', function () {
    expect(AppointmentStatus::Requested->getColor())->toBe('gray')
        ->and(AppointmentStatus::Confirmed->getColor())->toBe('primary')
        ->and(AppointmentStatus::Completed->getColor())->toBe('success')
        ->and(AppointmentStatus::Cancelled->getColor())->toBe('danger')
        ->and(AppointmentStatus::NoShow->getColor())->toBe('warning');
});

it('exposes a non-empty italian label for every case', function () {
    foreach (AppointmentStatus::cases() as $case) {
        expect($case->getLabel())->toBeString()->not->toBe('');
    }
});

it('exposes a non-empty color for every case', function () {
    foreach (AppointmentStatus::cases() as $case) {
        expect($case->getColor())->toBeString()->not->toBe('');
    }
});

it('allows the expected transitions from each state', function (AppointmentStatus $from, array $expected) {
    expect($from->allowedTransitions())->toEqualCanonicalizing($expected);
})->with([
    'requested' => [AppointmentStatus::Requested, [
        AppointmentStatus::Confirmed,
        AppointmentStatus::Cancelled,
    ]],
    'confirmed' => [AppointmentStatus::Confirmed, [
        AppointmentStatus::Completed,
        AppointmentStatus::Cancelled,
        AppointmentStatus::NoShow,
    ]],
    'completed (terminal)' => [AppointmentStatus::Completed, []],
    'cancelled (terminal)' => [AppointmentStatus::Cancelled, []],
    'no_show (terminal)' => [AppointmentStatus::NoShow, []],
]);

it('confirms every allowed edge via canTransitionTo', function () {
    expect(AppointmentStatus::Requested->canTransitionTo(AppointmentStatus::Confirmed))->toBeTrue()
        ->and(AppointmentStatus::Requested->canTransitionTo(AppointmentStatus::Cancelled))->toBeTrue()
        ->and(AppointmentStatus::Confirmed->canTransitionTo(AppointmentStatus::Completed))->toBeTrue()
        ->and(AppointmentStatus::Confirmed->canTransitionTo(AppointmentStatus::Cancelled))->toBeTrue()
        ->and(AppointmentStatus::Confirmed->canTransitionTo(AppointmentStatus::NoShow))->toBeTrue();
});

it('rejects forbidden edges via canTransitionTo', function () {
    expect(AppointmentStatus::Requested->canTransitionTo(AppointmentStatus::Completed))->toBeFalse()
        ->and(AppointmentStatus::Requested->canTransitionTo(AppointmentStatus::NoShow))->toBeFalse()
        ->and(AppointmentStatus::Requested->canTransitionTo(AppointmentStatus::Requested))->toBeFalse()
        ->and(AppointmentStatus::Confirmed->canTransitionTo(AppointmentStatus::Requested))->toBeFalse()
        ->and(AppointmentStatus::Confirmed->canTransitionTo(AppointmentStatus::Confirmed))->toBeFalse();
});

it('treats completed, cancelled and no_show as terminal states', function (AppointmentStatus $terminal) {
    expect($terminal->allowedTransitions())->toBe([]);

    foreach (AppointmentStatus::cases() as $target) {
        expect($terminal->canTransitionTo($target))->toBeFalse();
    }
})->with([
    'completed' => [AppointmentStatus::Completed],
    'cancelled' => [AppointmentStatus::Cancelled],
    'no_show' => [AppointmentStatus::NoShow],
]);
