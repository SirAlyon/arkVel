<?php

declare(strict_types=1);

use App\Enums\AnimalStatus;
use App\Enums\ApplicationStatus;
use App\Enums\Species;

it('exposes italian labels for animal statuses', function () {
    expect(AnimalStatus::Available->getLabel())->toBe('Disponibile')
        ->and(AnimalStatus::Adopted->getLabel())->toBe('Adottato');
});

it('exposes a color for every application status', function () {
    foreach (ApplicationStatus::cases() as $status) {
        expect($status->getColor())->toBeString()->not->toBeEmpty();
    }
});

it('supports dogs, cats and other species', function () {
    expect(Species::cases())->toHaveCount(3);
});
