<?php

declare(strict_types=1);

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
 | IP del visitatore dietro un proxy (config/trustedproxy.php, TRUSTED_PROXIES): è l'IP su
 | cui contano i rate limit per IP dell'autenticazione pubblica (App\Actions\Auth).
 */

beforeEach(function (): void {
    Route::get('/_test/ip', fn (Request $request): string => (string) $request->ip());
});

it('uses the connection IP when no proxy is trusted', function (): void {
    config(['trustedproxy.proxies' => null]);

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
        ->withHeader('X-Forwarded-For', '203.0.113.9')
        ->get('/_test/ip')
        ->assertSee('10.0.0.5');
});

it('uses the forwarded client IP behind a trusted proxy', function (): void {
    config(['trustedproxy.proxies' => '10.0.0.0/8']);

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
        ->withHeader('X-Forwarded-For', '203.0.113.9')
        ->get('/_test/ip')
        ->assertSee('203.0.113.9');
});

it('ignores the forwarded header from an untrusted address', function (): void {
    config(['trustedproxy.proxies' => '10.0.0.0/8']);

    $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.7'])
        ->withHeader('X-Forwarded-For', '203.0.113.9')
        ->get('/_test/ip')
        ->assertSee('198.51.100.7');
});
