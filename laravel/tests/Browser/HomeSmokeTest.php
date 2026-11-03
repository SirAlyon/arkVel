<?php

declare(strict_types=1);

use App\Models\Animal;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 | Smoke test browser (Pest 4 Browser + Playwright/Chromium).
 | Gira SOLO nel servizio `browser-tests`: `docker compose run --rm browser-tests`.
 */

it('renders the home page in a real browser without JavaScript errors', function () {
    $organization = Organization::factory()->verified()->create();
    Animal::factory()->available()->featured()->forOrganization($organization)->create(['name' => 'Fiocco']);

    visit(route('home'))
        ->withLocale('it-IT')
        ->assertTitle('ArkVel — Adotta un amico')
        ->assertSee('Fiocco')
        ->assertNoJavaScriptErrors();
});

it('renders the home page on a mobile viewport', function () {
    visit(route('home'))
        ->withLocale('it-IT')
        ->on()->mobile()
        ->assertTitle('ArkVel — Adotta un amico')
        ->assertNoJavaScriptErrors();
});

it('serves English to a browser that prefers it', function () {
    visit(route('home'))
        ->withLocale('en-GB')
        ->assertTitle('ArkVel — Adopt a friend');
});
