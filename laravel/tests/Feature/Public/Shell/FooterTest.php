<?php

declare(strict_types=1);

use App\Http\Middleware\SetLocale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

/*
 | Footer della shell pubblica (UI-3): link, newsletter segnaposto, note legali senza link
 | rotti, lingua, tema solo sulle pagine themeable, copyright.
 */

function siteFooterOf(TestResponse|string $response): string
{
    $html = is_string($response) ? $response : (string) $response->getContent();
    $start = strpos($html, 'data-site-footer');
    $end = strrpos($html, '</footer>');

    expect($start)->not->toBeFalse()->and($end)->not->toBeFalse();

    return substr($html, (int) $start, (int) $end - (int) $start);
}

it('links the catalog, the organizations and the adopter area', function (): void {
    $footer = siteFooterOf(test()->get(route('home')));

    expect($footer)
        ->toContain('aria-label="Collegamenti"')
        ->toMatch('/href="'.preg_quote(route('catalog'), '/').'"[^>]*wire:navigate\.hover/')
        ->toMatch('/href="'.preg_quote(route('organizations.index'), '/').'"[^>]*wire:navigate\.hover/')
        ->toContain('href="'.route('filament.adopter.home').'"')
        ->toContain('href="'.route('login').'"')
        ->toContain('href="'.route('register').'"');
});

it('hides login and sign-up from signed-in users', function (): void {
    $footer = siteFooterOf(test()->actingAs(User::factory()->create())->get(route('home')));

    expect($footer)
        ->toContain('href="'.route('filament.adopter.home').'"')
        ->not->toContain(route('login'))
        ->not->toContain(route('register'));
});

it('shows the newsletter as a disabled placeholder that says it is coming soon', function (): void {
    $footer = siteFooterOf(test()->get(route('home')));
    $newsletter = substr($footer, (int) strpos($footer, 'data-newsletter'));
    $newsletter = substr($newsletter, 0, (int) strpos($newsletter, '</section>'));

    expect($newsletter)
        ->toContain('id="footer-newsletter"')
        ->toContain('<fieldset disabled')
        ->toContain('L&#039;iscrizione non è ancora attiva: la newsletter è in arrivo.')
        ->toContain('In arrivo')
        ->not->toMatch('/<form[^>]*action=/');
});

it('links the legal pages now that they exist', function (): void {
    $footer = siteFooterOf(test()->get(route('home')));

    $legal = substr($footer, (int) strpos($footer, 'data-legal-links'));
    $legal = substr($legal, 0, (int) strpos($legal, '</ul>'));

    expect($legal)
        ->toContain('Informativa privacy')
        ->toContain('Termini di servizio')
        ->toContain('href="'.route('legal.privacy').'"')
        ->toContain('href="'.route('legal.terms').'"')
        ->not->toContain('href="#"')
        ->not->toContain('In arrivo');
});

it('shows the language switcher and the copyright', function (): void {
    // Una pagina senza opt-in del tema (le pagine migrate in UI-6 sono themeable).
    $footer = siteFooterOf((string) test()->blade('@component("layouts::app", []) <p>Corpo</p> @endcomponent'));

    expect($footer)
        ->toContain('data-language-switcher')
        ->toMatch('/lang="it"\s+aria-pressed="true"[^>]*>\s*Italiano\s*</')
        ->toContain('© '.now()->year.' ArkVel. Tutti i diritti riservati.')
        ->not->toContain('data-theme-option="');
});

it('shows the theme selector only on themeable pages', function (): void {
    $footer = siteFooterOf((string) test()->blade('@component("layouts::app", ["themeable" => true]) <p>Corpo</p> @endcomponent'));

    expect($footer)->toContain('data-theme-option="dark"');
});

it('translates the footer into English', function (): void {
    $footer = siteFooterOf(test()->withSession([SetLocale::SESSION_KEY => 'en'])->get(route('home')));

    expect($footer)
        ->toContain('Explore')
        ->toContain('Privacy policy')
        ->toContain('Coming soon')
        ->toContain('All rights reserved.');
});
