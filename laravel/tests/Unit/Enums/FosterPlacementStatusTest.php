<?php

declare(strict_types=1);

use App\Enums\FosterPlacementStatus;

it('is backed by the expected string values', function () {
    expect(FosterPlacementStatus::Active->value)->toBe('active')
        ->and(FosterPlacementStatus::Ended->value)->toBe('ended');
});

it('defines exactly two statuses', function () {
    expect(FosterPlacementStatus::cases())->toHaveCount(2);
});

it('pins the italian labels', function () {
    expect(FosterPlacementStatus::Active->getLabel())->toBe('In corso')
        ->and(FosterPlacementStatus::Ended->getLabel())->toBe('Concluso');
});

it('exposes a non-empty italian label for every case', function () {
    foreach (FosterPlacementStatus::cases() as $case) {
        expect($case->getLabel())->toBeString()->not->toBe('');
    }
});

it('exposes a non-empty color for every case', function () {
    foreach (FosterPlacementStatus::cases() as $case) {
        expect($case->getColor())->toBeString()->not->toBe('');
    }
});
