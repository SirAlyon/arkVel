<?php

declare(strict_types=1);

use App\Enums\MembershipRole;
use App\Enums\RoleName;

it('is backed by the expected string values', function () {
    expect(MembershipRole::Owner->value)->toBe('owner')
        ->and(MembershipRole::Staff->value)->toBe('staff')
        ->and(MembershipRole::Volunteer->value)->toBe('volunteer');
});

it('exposes italian labels for every case', function () {
    expect(MembershipRole::Owner->getLabel())->toBe('Titolare')
        ->and(MembershipRole::Staff->getLabel())->toBe('Staff')
        ->and(MembershipRole::Volunteer->getLabel())->toBe('Volontario');
});

it('maps every case to the matching organization-scoped role', function () {
    expect(MembershipRole::Owner->toRoleName())->toBe(RoleName::OrgOwner)
        ->and(MembershipRole::Staff->toRoleName())->toBe(RoleName::OrgStaff)
        ->and(MembershipRole::Volunteer->toRoleName())->toBe(RoleName::OrgVolunteer);
});

it('only maps to organization-scoped roles', function () {
    foreach (MembershipRole::cases() as $case) {
        expect($case->toRoleName()->isOrganizationScoped())->toBeTrue();
    }
});
