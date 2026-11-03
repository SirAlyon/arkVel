<?php

declare(strict_types=1);

use App\Enums\AppointmentType;

it('is backed by the expected string values', function () {
    expect(AppointmentType::Visit->value)->toBe('visit')
        ->and(AppointmentType::Interview->value)->toBe('interview')
        ->and(AppointmentType::HomeCheck->value)->toBe('home_check');
});

it('defines exactly three types', function () {
    expect(AppointmentType::cases())->toHaveCount(3);
});

it('pins the italian labels', function () {
    expect(AppointmentType::Visit->getLabel())->toBe('Visita')
        ->and(AppointmentType::Interview->getLabel())->toBe('Colloquio')
        ->and(AppointmentType::HomeCheck->getLabel())->toBe('Controllo domiciliare');
});

it('pins the colors', function () {
    expect(AppointmentType::Visit->getColor())->toBe('success')
        ->and(AppointmentType::Interview->getColor())->toBe('info')
        ->and(AppointmentType::HomeCheck->getColor())->toBe('warning');
});

it('exposes a non-empty italian label for every case', function () {
    foreach (AppointmentType::cases() as $case) {
        expect($case->getLabel())->toBeString()->not->toBe('');
    }
});

it('exposes a non-empty color for every case', function () {
    foreach (AppointmentType::cases() as $case) {
        expect($case->getColor())->toBeString()->not->toBe('');
    }
});
