<?php

declare(strict_types=1);

use App\Support\Site\LegalLinks;
use Illuminate\Support\Facades\Route;

it('has no privacy policy link while the legal page does not exist', function (): void {
    expect(app(LegalLinks::class)->privacyPolicy())->toBeNull();
});

it('links the privacy policy once its route exists', function (): void {
    Route::get('/privacy', fn (): string => 'privacy')->name(LegalLinks::PRIVACY_POLICY_ROUTE);
    app('router')->getRoutes()->refreshNameLookups();

    expect(app(LegalLinks::class)->privacyPolicy())->toBe(url('/privacy'));
});

it('gives the privacy policy version from the config, with a default', function (): void {
    expect(app(LegalLinks::class)->privacyPolicyVersion())->toBe('2026-11');

    config(['arkvel.privacy_policy_version' => '2027-03']);
    expect(app(LegalLinks::class)->privacyPolicyVersion())->toBe('2027-03');

    config(['arkvel.privacy_policy_version' => '']);
    expect(app(LegalLinks::class)->privacyPolicyVersion())->toBe('2026-11');
});
