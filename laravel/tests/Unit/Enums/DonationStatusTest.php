<?php

declare(strict_types=1);

use App\Enums\DonationStatus;

it('is backed by the expected string values', function () {
    expect(DonationStatus::Pending->value)->toBe('pending')
        ->and(DonationStatus::Completed->value)->toBe('completed')
        ->and(DonationStatus::Failed->value)->toBe('failed')
        ->and(DonationStatus::Refunded->value)->toBe('refunded')
        ->and(DonationStatus::Canceled->value)->toBe('canceled');
});

it('defines exactly five statuses', function () {
    expect(DonationStatus::cases())->toHaveCount(5);
});

it('pins the italian labels', function () {
    expect(DonationStatus::Pending->getLabel())->toBe('In attesa')
        ->and(DonationStatus::Completed->getLabel())->toBe('Completata')
        ->and(DonationStatus::Failed->getLabel())->toBe('Fallita')
        ->and(DonationStatus::Refunded->getLabel())->toBe('Rimborsata')
        ->and(DonationStatus::Canceled->getLabel())->toBe('Annullata');
});

it('pins the colors', function () {
    expect(DonationStatus::Pending->getColor())->toBe('warning')
        ->and(DonationStatus::Completed->getColor())->toBe('success')
        ->and(DonationStatus::Failed->getColor())->toBe('danger')
        ->and(DonationStatus::Refunded->getColor())->toBe('info')
        ->and(DonationStatus::Canceled->getColor())->toBe('gray');
});

it('exposes a non-empty italian label for every case', function () {
    foreach (DonationStatus::cases() as $case) {
        expect($case->getLabel())->toBeString()->not->toBe('');
    }
});

it('exposes a non-empty color for every case', function () {
    foreach (DonationStatus::cases() as $case) {
        expect($case->getColor())->toBeString()->not->toBe('');
    }
});

it('allows the expected transitions from each state', function (DonationStatus $from, array $expected) {
    expect($from->allowedTransitions())->toEqualCanonicalizing($expected);
})->with([
    'pending' => [DonationStatus::Pending, [
        DonationStatus::Completed,
        DonationStatus::Failed,
        DonationStatus::Canceled,
    ]],
    'completed' => [DonationStatus::Completed, [
        DonationStatus::Refunded,
    ]],
    'failed (terminal)' => [DonationStatus::Failed, []],
    'canceled (terminal)' => [DonationStatus::Canceled, []],
    'refunded (terminal)' => [DonationStatus::Refunded, []],
]);

it('confirms every allowed edge via canTransitionTo', function () {
    expect(DonationStatus::Pending->canTransitionTo(DonationStatus::Completed))->toBeTrue()
        ->and(DonationStatus::Pending->canTransitionTo(DonationStatus::Failed))->toBeTrue()
        ->and(DonationStatus::Pending->canTransitionTo(DonationStatus::Canceled))->toBeTrue()
        ->and(DonationStatus::Completed->canTransitionTo(DonationStatus::Refunded))->toBeTrue();
});

it('rejects forbidden edges via canTransitionTo', function () {
    expect(DonationStatus::Pending->canTransitionTo(DonationStatus::Refunded))->toBeFalse()
        ->and(DonationStatus::Pending->canTransitionTo(DonationStatus::Pending))->toBeFalse()
        ->and(DonationStatus::Completed->canTransitionTo(DonationStatus::Completed))->toBeFalse()
        ->and(DonationStatus::Completed->canTransitionTo(DonationStatus::Failed))->toBeFalse()
        ->and(DonationStatus::Completed->canTransitionTo(DonationStatus::Canceled))->toBeFalse()
        ->and(DonationStatus::Completed->canTransitionTo(DonationStatus::Pending))->toBeFalse();
});

it('treats failed, canceled and refunded as terminal states', function (DonationStatus $terminal) {
    expect($terminal->allowedTransitions())->toBe([]);

    foreach (DonationStatus::cases() as $target) {
        expect($terminal->canTransitionTo($target))->toBeFalse();
    }
})->with([
    'failed' => [DonationStatus::Failed],
    'canceled' => [DonationStatus::Canceled],
    'refunded' => [DonationStatus::Refunded],
]);
