<?php

declare(strict_types=1);

use App\Support\Site\LegalLinks;

it('links the privacy policy and the terms of service to their routes', function (): void {
    $links = app(LegalLinks::class);

    expect($links->privacyPolicy())->toBe(route(LegalLinks::PRIVACY_POLICY_ROUTE))
        ->and($links->termsOfService())->toBe(route(LegalLinks::TERMS_ROUTE));
});

it('gives the privacy policy version from the config, with a default', function (): void {
    expect(app(LegalLinks::class)->privacyPolicyVersion())->toBe('2026-11');

    config(['arkvel.privacy_policy_version' => '2027-03']);
    expect(app(LegalLinks::class)->privacyPolicyVersion())->toBe('2027-03');

    config(['arkvel.privacy_policy_version' => '']);
    expect(app(LegalLinks::class)->privacyPolicyVersion())->toBe('2026-11');
});

it('gives the terms of service version from the config, with a default', function (): void {
    expect(app(LegalLinks::class)->termsVersion())->toBe('2026-11');

    config(['arkvel.terms_version' => '2027-05']);
    expect(app(LegalLinks::class)->termsVersion())->toBe('2027-05');

    config(['arkvel.terms_version' => '']);
    expect(app(LegalLinks::class)->termsVersion())->toBe('2026-11');
});
