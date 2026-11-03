<?php

declare(strict_types=1);

use App\Support\DesignTokens\ContrastPairs;

/*
 | /_styleguide: strumento di sviluppo, disponibile solo in ambiente `local`.
 */

function styleguideInEnvironment(string $environment): void
{
    app()->detectEnvironment(static fn (): string => $environment);
}

it('is not reachable outside the local environment', function (string $environment): void {
    styleguideInEnvironment($environment);

    $this->get('/_styleguide')->assertNotFound();
})->with(['testing', 'staging', 'production']);

it('renders the design tokens in the local environment', function (): void {
    styleguideInEnvironment('local');

    $response = $this->get(route('styleguide'));

    $response->assertOk()
        ->assertHeader('content-type', 'text/html; charset=utf-8')
        ->assertSee('<meta name="robots" content="noindex, nofollow">', escape: false)
        ->assertSee('window.arkvelTheme', escape: false)
        ->assertSeeInOrder(['id="preview"', 'id="colors"', 'id="contrast"', 'id="typography"', 'id="shapes"', 'id="spacing"', 'id="motion"'], escape: false)
        ->assertSee('Trova il tuo nuovo migliore amico.');

    expect(substr_count($response->getContent() ?: '', 'data-contrast="'))->toBe(count(ContrastPairs::all()));
});

it('is translated in English', function (): void {
    styleguideInEnvironment('local');

    $this->withSession(['locale' => 'en'])
        ->get(route('styleguide'))
        ->assertOk()
        ->assertSee('<html lang="en">', escape: false)
        ->assertSee('Find your new best friend.')
        ->assertSee('Light')
        ->assertDontSee('Trova il tuo nuovo migliore amico.');
});

it('documents each component group on its own page', function (): void {
    styleguideInEnvironment('local');

    $this->get(route('styleguide.group', 'actions'))
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex, nofollow">', escape: false)
        ->assertSee('aria-current="page"', escape: false)
        ->assertSeeInOrder(['id="icon"', 'id="button"', 'id="link"', 'id="badge"', 'id="tag"'], escape: false);

    $this->get(route('styleguide'))
        ->assertOk()
        ->assertSee(route('styleguide.group', 'actions'), escape: false);
});

it('answers 404 for unknown component groups', function (string $group): void {
    styleguideInEnvironment('local');

    $this->get('/_styleguide/'.$group)->assertNotFound();
})->with(['unknown', 'colors', 'Actions']);

it('hides the component pages outside the local environment', function (): void {
    styleguideInEnvironment('production');

    $this->get('/_styleguide/actions')->assertNotFound();
});
