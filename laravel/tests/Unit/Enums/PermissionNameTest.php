<?php

declare(strict_types=1);

use App\Enums\PermissionName;

it('is backed by the expected string values', function () {
    expect(PermissionName::OrganizationUpdate->value)->toBe('organization.update')
        ->and(PermissionName::OrganizationManageStaff->value)->toBe('organization.manage_staff')
        ->and(PermissionName::AnimalView->value)->toBe('animal.view')
        ->and(PermissionName::AnimalCreate->value)->toBe('animal.create')
        ->and(PermissionName::AnimalUpdate->value)->toBe('animal.update')
        ->and(PermissionName::AnimalDelete->value)->toBe('animal.delete')
        ->and(PermissionName::MedicalRecordView->value)->toBe('medical_record.view')
        ->and(PermissionName::MedicalRecordCreate->value)->toBe('medical_record.create')
        ->and(PermissionName::MedicalRecordUpdate->value)->toBe('medical_record.update')
        ->and(PermissionName::MedicalRecordDelete->value)->toBe('medical_record.delete');
});

it('defines exactly ten phase-1 permissions', function () {
    expect(PermissionName::cases())->toHaveCount(10);
});

it('exposes a non-empty italian label for every case', function () {
    foreach (PermissionName::cases() as $case) {
        expect($case->getLabel())->toBeString()->not->toBe('');
    }
});
