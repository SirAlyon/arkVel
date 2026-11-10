<?php

declare(strict_types=1);

use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 | Pagine legali pubbliche (/privacy, /termini): rispondono 200, hanno un solo h1, dichiarano
 | la lingua servita e sono tradotte in IT ed EN. Vedi App\Livewire\Legal.
 */

dataset('legal pages', [
    'privacy' => ['legal.privacy', 'Informativa sulla privacy', 'Privacy policy'],
    'terms' => ['legal.terms', 'Termini di servizio', 'Terms of service'],
]);

it('serves the page in Italian with a single h1 and the served language', function (string $route, string $italianTitle): void {
    $response = $this->get(route($route));

    $response->assertOk()->assertHeader('Content-Language', 'it');

    $html = (string) $response->getContent();

    expect(substr_count($html, '<h1'))->toBe(1);
    expect($html)
        ->toContain('id="legal-title"')
        ->toContain($italianTitle);
})->with('legal pages');

it('serves the page in English when the session language is English', function (string $route, string $italianTitle, string $englishTitle): void {
    $response = $this->withSession([SetLocale::SESSION_KEY => 'en'])->get(route($route));

    $response->assertOk()->assertHeader('Content-Language', 'en');

    expect((string) $response->getContent())
        ->toContain($englishTitle)
        ->not->toContain($italianTitle);
})->with('legal pages');

it('marks the content as a draft to be validated', function (string $route): void {
    expect((string) $this->get(route($route))->getContent())
        ->toContain('data-legal-draft')
        ->toContain('Bozza da validare');
})->with('legal pages');

it('shows the version in force and is indexable', function (string $route): void {
    $response = $this->get(route($route));

    expect((string) $response->getContent())
        ->toContain('Versione 2026-11')
        ->toContain('Ultimo aggiornamento');

    // Pagine legali indicizzabili: nessun meta robots noindex.
    $response->assertDontSee('name="robots"', false);
})->with('legal pages');

it('exposes a table of contents anchored to every section', function (string $route): void {
    $html = (string) $this->get(route($route))->getContent();

    expect($html)
        ->toContain('data-legal-toc')
        // La prima voce dell'indice punta a una sezione con lo stesso id.
        ->toMatch('/href="#([a-z]+)"/');
})->with('legal pages');
