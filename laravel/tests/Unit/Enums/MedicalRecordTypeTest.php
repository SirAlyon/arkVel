<?php

declare(strict_types=1);

use App\Enums\MedicalRecordType;

it('is backed by the expected string values', function () {
    expect(MedicalRecordType::Vaccination->value)->toBe('vaccination')
        ->and(MedicalRecordType::Treatment->value)->toBe('treatment')
        ->and(MedicalRecordType::Surgery->value)->toBe('surgery')
        ->and(MedicalRecordType::Checkup->value)->toBe('checkup')
        ->and(MedicalRecordType::Deworming->value)->toBe('deworming');
});

it('exposes italian labels for every case', function () {
    expect(MedicalRecordType::Vaccination->getLabel())->toBe('Vaccinazione')
        ->and(MedicalRecordType::Treatment->getLabel())->toBe('Trattamento')
        ->and(MedicalRecordType::Surgery->getLabel())->toBe('Intervento chirurgico')
        ->and(MedicalRecordType::Checkup->getLabel())->toBe('Visita di controllo')
        ->and(MedicalRecordType::Deworming->getLabel())->toBe('Sverminazione');
});

it('exposes a color for every case', function () {
    expect(MedicalRecordType::Vaccination->getColor())->toBe('success')
        ->and(MedicalRecordType::Treatment->getColor())->toBe('info')
        ->and(MedicalRecordType::Surgery->getColor())->toBe('danger')
        ->and(MedicalRecordType::Checkup->getColor())->toBe('gray')
        ->and(MedicalRecordType::Deworming->getColor())->toBe('warning');
});
