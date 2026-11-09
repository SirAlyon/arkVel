<?php

declare(strict_types=1);

use App\Support\Http\TrustedHosts;

it('trusts exactly the APP_URL host, without its subdomains', function (): void {
    $patterns = TrustedHosts::patterns('https://arkvel.example:8443/sub');

    expect($patterns)->toBe(['^arkvel\.example$'])
        ->and(preg_match('{'.$patterns[0].'}i', 'arkvel.example'))->toBe(1)
        ->and(preg_match('{'.$patterns[0].'}i', 'evil.arkvel.example'))->toBe(0)
        ->and(preg_match('{'.$patterns[0].'}i', 'arkvel.example.evil'))->toBe(0)
        ->and(preg_match('{'.$patterns[0].'}i', 'arkvelXexample'))->toBe(0);
});

it('adds the extra hosts, ignoring blanks and duplicates', function (): void {
    expect(TrustedHosts::patterns('https://arkvel.example', [' www.arkvel.example ', '', 'ARKVEL.example', 42]))
        ->toBe(['^arkvel\.example$', '^www\.arkvel\.example$']);
});

it('trusts nothing extra when APP_URL has no host', function (): void {
    expect(TrustedHosts::patterns('not a url'))->toBe([]);
});
