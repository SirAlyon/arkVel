<?php

declare(strict_types=1);

use App\Enums\RoleName;

it('is backed by the expected string values', function () {
    expect(RoleName::SuperAdmin->value)->toBe('Super Admin')
        ->and(RoleName::OrgOwner->value)->toBe('Org Owner')
        ->and(RoleName::OrgStaff->value)->toBe('Org Staff')
        ->and(RoleName::OrgVolunteer->value)->toBe('Org Volunteer')
        ->and(RoleName::Adopter->value)->toBe('Adopter');
});

it('exposes italian labels for every case', function () {
    expect(RoleName::SuperAdmin->getLabel())->toBe('Super Amministratore')
        ->and(RoleName::OrgOwner->getLabel())->toBe('Titolare ente')
        ->and(RoleName::OrgStaff->getLabel())->toBe('Staff ente')
        ->and(RoleName::OrgVolunteer->getLabel())->toBe('Volontario ente')
        ->and(RoleName::Adopter->getLabel())->toBe('Adottante');
});

it('marks organization-scoped roles', function () {
    expect(RoleName::OrgOwner->isOrganizationScoped())->toBeTrue()
        ->and(RoleName::OrgStaff->isOrganizationScoped())->toBeTrue()
        ->and(RoleName::OrgVolunteer->isOrganizationScoped())->toBeTrue();
});

it('marks non organization-scoped roles', function () {
    expect(RoleName::SuperAdmin->isOrganizationScoped())->toBeFalse()
        ->and(RoleName::Adopter->isOrganizationScoped())->toBeFalse();
});
