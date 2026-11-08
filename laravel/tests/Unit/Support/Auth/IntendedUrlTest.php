<?php

declare(strict_types=1);

use App\Support\Auth\IntendedUrl;

/*
 | Destinazione dopo accesso/verifica: solo URL interni (niente open redirect).
 */

it('accepts internal destinations', function (string $url): void {
    expect(app(IntendedUrl::class)->isInternal($url))->toBeTrue();
})->with([
    'relative path' => '/animali/3?tab=foto',
    'root' => '/',
    'absolute on the site' => fn (): string => url('/account/preferiti'),
    'absolute, case of the host' => fn (): string => strtoupper(url('/account')),
]);

it('rejects external or malformed destinations', function (string $url): void {
    expect(app(IntendedUrl::class)->isInternal($url))->toBeFalse();
})->with([
    'empty' => '',
    'other host' => 'https://evil.example/account',
    'protocol relative' => '//evil.example/account',
    'backslash trick' => '/\\evil.example',
    'control character' => "/account\n//evil.example",
    'other scheme' => fn (): string => str_replace('http', 'ftp', url('/account')),
    'other port' => fn (): string => rtrim(url('/'), '/').':8443/account',
    'credentials' => fn (): string => preg_replace('#^(https?://)#', '$1user:pass@', url('/account')),
    'javascript' => 'javascript:alert(1)',
    'no host' => 'mailto:someone@example.com',
    'unparsable' => 'http:///account',
]);

it('pulls the intended URL from the session or falls back to the default', function (): void {
    $session = app('session.store');
    $intended = app(IntendedUrl::class);

    $session->put(IntendedUrl::SESSION_KEY, '/animali/3');
    expect($intended->pull($session, '/account'))->toBe('/animali/3')
        ->and($session->has(IntendedUrl::SESSION_KEY))->toBeFalse()
        ->and($intended->pull($session, '/account'))->toBe('/account');

    $session->put(IntendedUrl::SESSION_KEY, 'https://evil.example');
    expect($intended->pull($session, '/account'))->toBe('/account')
        ->and($session->has(IntendedUrl::SESSION_KEY))->toBeFalse();

    $session->put(IntendedUrl::SESSION_KEY, ['not', 'a', 'string']);
    expect($intended->pull($session, '/account'))->toBe('/account');
});
