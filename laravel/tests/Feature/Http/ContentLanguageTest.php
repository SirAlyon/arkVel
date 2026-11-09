<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 | Header di lingua sulle pagine HTML pubbliche (App\Http\Middleware\ContentLanguage).
 */

it('declares the served language and varies on Accept-Language', function (): void {
    $response = $this->get(route('home'));

    $response->assertOk()->assertHeader('Content-Language', 'it');
    expect($response->headers->get('Vary'))->toContain('Accept-Language');
});

it('follows the language chosen for the session', function (): void {
    $this->withSession(['locale' => 'en'])
        ->get(route('catalog'))
        ->assertHeader('Content-Language', 'en');
});

it('leaves non HTML responses alone', function (string $path): void {
    $response = $this->get($path);

    $response->assertOk()->assertHeaderMissing('Content-Language');
    expect((string) $response->headers->get('Vary'))->not->toContain('Accept-Language');
})->with(['/sitemap.xml', '/robots.txt']);
