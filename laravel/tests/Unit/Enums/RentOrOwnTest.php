<?php

declare(strict_types=1);

use App\Enums\RentOrOwn;

it('is backed by the expected string values', function () {
    expect(RentOrOwn::Own->value)->toBe('own')
        ->and(RentOrOwn::Rent->value)->toBe('rent');
});

it('exposes italian labels for every case', function () {
    expect(RentOrOwn::Own->getLabel())->toBe('Di proprietà')
        ->and(RentOrOwn::Rent->getLabel())->toBe('In affitto');
});
