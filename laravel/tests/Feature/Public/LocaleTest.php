<?php

declare(strict_types=1);

use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('defaults to Italian when the visitor expresses no preference', function () {
    $this->withHeader('Accept-Language', '')
        ->get(route('home'))
        ->assertOk()
        ->assertSee('<html lang="it"', escape: false)
        ->assertSee('<title>ArkVel — Adotta un amico</title>', escape: false);
});

it('follows the browser Accept-Language header among supported locales', function () {
    $this->withHeader('Accept-Language', 'en-GB,en;q=0.9')
        ->get(route('catalog'))
        ->assertOk()
        ->assertSee('<html lang="en"', escape: false)
        ->assertSee('<title>Catalog — ArkVel</title>', escape: false);
});

it('falls back to the default locale for an unsupported browser language', function () {
    $this->withHeader('Accept-Language', 'ja-JP,ja;q=0.9')
        ->get(route('organizations.index'))
        ->assertOk()
        ->assertSee('<title>Enti — ArkVel</title>', escape: false);
});

it('picks the first supported language in the browser preference order', function () {
    $this->withHeader('Accept-Language', 'de-DE,de;q=0.9,en;q=0.8,it;q=0.7')
        ->get(route('home'))
        ->assertSee('<html lang="en"', escape: false);
});

it('prefers the locale chosen in session over the browser header', function () {
    $this->withSession([SetLocale::SESSION_KEY => 'en'])
        ->withHeader('Accept-Language', 'it-IT')
        ->get(route('organizations.index'))
        ->assertSee('<title>Organizations — ArkVel</title>', escape: false);
});

it('ignores an unsupported locale stored in session', function () {
    $this->withSession([SetLocale::SESSION_KEY => 'xx'])
        ->withHeader('Accept-Language', '')
        ->get(route('home'))
        ->assertSee('<html lang="it"', escape: false);
});

it('stores the chosen locale and redirects back', function () {
    $this->from(route('catalog'))
        ->post(route('locale.update', ['locale' => 'en']))
        ->assertRedirect(route('catalog'))
        ->assertSessionHas(SetLocale::SESSION_KEY, 'en');

    $this->get(route('catalog'))->assertSee('<html lang="en"', escape: false);
});

it('redirects home when switching locale without a referrer', function () {
    $this->post(route('locale.update', ['locale' => 'it']))
        ->assertRedirect(route('home'));
});

it('rejects an unsupported locale', function () {
    $this->post(route('locale.update', ['locale' => 'fr']))->assertNotFound();
});
