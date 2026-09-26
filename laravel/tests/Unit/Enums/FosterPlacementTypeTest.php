<?php

declare(strict_types=1);

use App\Enums\FosterPlacementType;

it('is backed by the expected string values', function () {
    expect(FosterPlacementType::PreAdoption->value)->toBe('pre_adoption')
        ->and(FosterPlacementType::Medical->value)->toBe('medical')
        ->and(FosterPlacementType::Emergency->value)->toBe('emergency')
        ->and(FosterPlacementType::LongTerm->value)->toBe('long_term');
});

it('defines exactly four types', function () {
    expect(FosterPlacementType::cases())->toHaveCount(4);
});

it('pins the italian labels', function () {
    expect(FosterPlacementType::PreAdoption->getLabel())->toBe('Pre-adozione')
        ->and(FosterPlacementType::Medical->getLabel())->toBe('Sanitario')
        ->and(FosterPlacementType::Emergency->getLabel())->toBe('Emergenza')
        ->and(FosterPlacementType::LongTerm->getLabel())->toBe('Lungo termine');
});

it('exposes a non-empty italian label for every case', function () {
    foreach (FosterPlacementType::cases() as $case) {
        expect($case->getLabel())->toBeString()->not->toBe('');
    }
});

it('exposes a non-empty color for every case', function () {
    foreach (FosterPlacementType::cases() as $case) {
        expect($case->getColor())->toBeString()->not->toBe('');
    }
});
