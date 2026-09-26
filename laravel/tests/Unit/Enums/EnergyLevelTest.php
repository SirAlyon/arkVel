<?php

declare(strict_types=1);

use App\Enums\EnergyLevel;

it('has the expected string values', function () {
    expect(EnergyLevel::Low->value)->toBe('low')
        ->and(EnergyLevel::Medium->value)->toBe('medium')
        ->and(EnergyLevel::High->value)->toBe('high');
});

it('exposes exactly three cases', function () {
    expect(EnergyLevel::cases())->toHaveCount(3);
});

it('returns italian labels for every case', function (EnergyLevel $level, string $label) {
    expect($level->getLabel())->toBe($label);
})->with([
    'low' => [EnergyLevel::Low, 'Basso'],
    'medium' => [EnergyLevel::Medium, 'Medio'],
    'high' => [EnergyLevel::High, 'Alto'],
]);
