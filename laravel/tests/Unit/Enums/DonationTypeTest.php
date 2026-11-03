<?php

declare(strict_types=1);

use App\Enums\DonationType;

it('is backed by the expected string values', function () {
    expect(DonationType::OneOff->value)->toBe('one_off')
        ->and(DonationType::Recurring->value)->toBe('recurring');
});

it('defines exactly two types', function () {
    expect(DonationType::cases())->toHaveCount(2);
});

it('pins the italian labels', function () {
    expect(DonationType::OneOff->getLabel())->toBe('Una tantum')
        ->and(DonationType::Recurring->getLabel())->toBe('Ricorrente');
});

it('pins the colors', function () {
    expect(DonationType::OneOff->getColor())->toBe('gray')
        ->and(DonationType::Recurring->getColor())->toBe('info');
});

it('exposes a non-empty italian label for every case', function () {
    foreach (DonationType::cases() as $case) {
        expect($case->getLabel())->toBeString()->not->toBe('');
    }
});

it('exposes a non-empty color for every case', function () {
    foreach (DonationType::cases() as $case) {
        expect($case->getColor())->toBeString()->not->toBe('');
    }
});
