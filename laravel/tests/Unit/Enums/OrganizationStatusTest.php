<?php

declare(strict_types=1);

use App\Enums\OrganizationStatus;

it('is backed by the expected string values', function () {
    expect(OrganizationStatus::Pending->value)->toBe('pending')
        ->and(OrganizationStatus::Verified->value)->toBe('verified')
        ->and(OrganizationStatus::Suspended->value)->toBe('suspended');
});

it('exposes italian labels for every case', function () {
    expect(OrganizationStatus::Pending->getLabel())->toBe('In attesa')
        ->and(OrganizationStatus::Verified->getLabel())->toBe('Verificato')
        ->and(OrganizationStatus::Suspended->getLabel())->toBe('Sospeso');
});

it('exposes the expected color for every case', function () {
    expect(OrganizationStatus::Pending->getColor())->toBe('warning')
        ->and(OrganizationStatus::Verified->getColor())->toBe('success')
        ->and(OrganizationStatus::Suspended->getColor())->toBe('danger');
});
