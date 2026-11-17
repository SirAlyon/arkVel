<?php

declare(strict_types=1);

use App\Exceptions\OrganizationIsDemo;
use App\Models\Organization;
use App\Support\Organizations\DemoOrganizationGuard;

// Guardia pura (nessun DB): un ente dimostrativo rifiuta ogni invio, uno normale no.

it('refuses a demo organization with a translated domain exception', function () {
    app()->setLocale('it');
    $organization = new Organization(['is_demo' => true]);

    expect(fn () => (new DemoOrganizationGuard)->ensureAcceptsSubmissions($organization))
        ->toThrow(OrganizationIsDemo::class, __('demo.refused'));
});

it('accepts a normal organization', function () {
    $organization = new Organization(['is_demo' => false]);

    (new DemoOrganizationGuard)->ensureAcceptsSubmissions($organization);

    expect(true)->toBeTrue();
});

it('carries the refused organization and an english message in english', function () {
    app()->setLocale('en');
    $organization = new Organization(['is_demo' => true]);

    $exception = OrganizationIsDemo::for($organization);

    expect($exception)->toBeInstanceOf(DomainException::class)
        ->and($exception->organization)->toBe($organization)
        ->and($exception->getMessage())->toContain('demo organisation');
});
