<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/*
 | Avviso pre-beta (x-site.prebeta-notice, incluso dal layout pubblico dopo il footer): un
 | modale informativo che alla prima visita spiega che il sito è un'anteprima. L'apertura e
 | il "ricordamelo" sono lato client (localStorage, x-model su uiDialog): qui verifichiamo
 | che il layout lo renda su una pagina pubblica reale, con titolo, eyebrow e azione.
 */

it('renders the pre-beta notice in the public layout', function (): void {
    $html = (string) test()->get(route('home'))->assertOk()->getContent();

    expect($html)
        ->toContain('data-prebeta-notice')
        ->toContain('data-dialog="prebeta"')
        ->toContain(__('prebeta.eyebrow'))
        ->toContain(__('prebeta.title'))
        ->toContain(__('prebeta.acknowledge'));
});

it('adds no extra toaster and keeps the notice after the footer', function (): void {
    $html = (string) test()->get(route('home'))->assertOk()->getContent();

    // Un solo toaster "vero": l'outlet dentro il dialog ha data-toaster-outlet, non data-toaster.
    expect(preg_match_all('/data-toaster(?![-\w])/', $html))->toBe(1)
        ->and(strpos($html, 'data-prebeta-notice'))->toBeGreaterThan(strrpos($html, 'data-site-footer'));
});
