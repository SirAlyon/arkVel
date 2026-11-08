<?php

declare(strict_types=1);

use App\Http\Middleware\SetLocale;
use App\Models\User;
use App\Support\Styleguide\StyleguideGroups;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 | Styleguide · gruppo "shell" (/_styleguide/shell, solo local): nav-link, language-switcher
 | e l'anteprima statica di header e footer. Nel browser: tests/Browser/Components/ShellComponentsTest.php.
 */

beforeEach(function (): void {
    app()->detectEnvironment(static fn (): string => 'local');
});

it('is listed before the media group', function (): void {
    expect(StyleguideGroups::available())->toContain('shell')
        ->and(array_search('shell', StyleguideGroups::ALL, true))->toBe(array_search('media', StyleguideGroups::ALL, true) - 1);
});

it('documents the shell components with their variants', function (): void {
    $this->get(route('styleguide.group', 'shell'))
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex, nofollow">', escape: false)
        ->assertSee('<h1 class="text-h1">Shell</h1>', escape: false)
        ->assertSeeInOrder(['id="nav-link"', 'id="language-switcher"', 'id="site-header"', 'id="site-footer"'], escape: false)
        ->assertSee('&lt;x-site.header&gt;', escape: false)
        ->assertSee('&lt;x-site.footer&gt;', escape: false)
        ->assertSee('aria-current="page"', escape: false)
        ->assertSee('aria-current="true"', escape: false)
        ->assertSee('data-demo="language-compact"', escape: false)
        ->assertSee('data-site-header', escape: false)
        ->assertSee('data-site-footer', escape: false);
});

it('previews header and footer as a guest even when signed in', function (): void {
    $html = (string) $this->actingAs(User::factory()->create(['name' => 'Giulia Bianchi']))
        ->get(route('styleguide.group', 'shell'))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('data-account-guest')
        ->toContain('data-account-login')
        ->not->toContain('data-account-menu')
        ->not->toContain('data-account-logout')
        ->not->toContain('Giulia');
});

it('is translated in English', function (): void {
    $this->withSession([SetLocale::SESSION_KEY => 'en'])
        ->get(route('styleguide.group', 'shell'))
        ->assertOk()
        ->assertSee('Navigation item')
        ->assertSee('Language switcher')
        ->assertDontSee('Voce di navigazione');
});
