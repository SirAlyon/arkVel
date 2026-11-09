<?php

declare(strict_types=1);
/*
 | Host header: fuori da local e dai test l'app risponde solo agli host fidati
 | (APP_URL + APP_TRUSTED_HOSTS, App\Support\Http\TrustedHosts), così nessun URL assoluto
 | (canonical, Open Graph, JSON-LD, link delle email) può seguire un host arbitrario.
 | La lista statica di Request è azzerata dopo ogni test (Tests\TestCase::tearDown).
 */

beforeEach(function (): void {
    config(['app.url' => 'http://arkvel.example', 'app.trusted_hosts' => ['www.arkvel.example'], 'app.debug' => false]);
    app()->detectEnvironment(static fn (): string => 'production');
});

it('rejects a request for a foreign host before generating any URL', function (string $path): void {
    $response = $this->get('http://evil.example'.$path);

    expect($response->getStatusCode())->toBe(400)
        ->and((string) $response->getContent())->not->toContain('evil.example');
})->with(['/', '/sitemap.xml', '/robots.txt']);

it('serves the application host and the extra trusted hosts', function (string $host): void {
    $this->get('http://'.$host.'/robots.txt')->assertOk()
        ->assertSee('Sitemap: http://arkvel.example/sitemap.xml');
})->with(['arkvel.example', 'www.arkvel.example']);

it('does not apply the list in the local environment', function (): void {
    app()->detectEnvironment(static fn (): string => 'local');

    $this->get('http://evil.example/robots.txt')->assertOk()
        ->assertSee('Sitemap: http://arkvel.example/sitemap.xml');
});
