<?php

declare(strict_types=1);

use App\Enums\HousingType;

it('is backed by the expected string values', function () {
    expect(HousingType::Apartment->value)->toBe('apartment')
        ->and(HousingType::House->value)->toBe('house')
        ->and(HousingType::Farm->value)->toBe('farm')
        ->and(HousingType::Other->value)->toBe('other');
});

it('exposes italian labels for every case', function () {
    expect(HousingType::Apartment->getLabel())->toBe('Appartamento')
        ->and(HousingType::House->getLabel())->toBe('Casa indipendente')
        ->and(HousingType::Farm->getLabel())->toBe('Casa rurale / con terreno')
        ->and(HousingType::Other->getLabel())->toBe('Altro');
});
