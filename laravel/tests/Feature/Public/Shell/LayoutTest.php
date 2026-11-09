<?php

declare(strict_types=1);

use App\Models\Animal;
use App\Models\Organization;
use App\Support\Site\FontPreload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Vite;
use Illuminate\Testing\TestView;

uses(RefreshDatabase::class);

/*
 | Layout pubblico (layouts::app, UI-3): asset, tema, SEO di ripiego, regione delle View
 | Transitions, toaster. Le pagine vere passano dai loro test; qui il layout è reso come lo
 | rende Livewire (@component con i dati di layout), per provare gli opt-in che nessuna
 | pagina usa ancora (themeable, transparentHeader).
 */

/**
 * @param  array<string, mixed>  $data
 */
function renderPublicLayout(array $data = []): TestView
{
    return test()->blade(
        '@component("layouts::app", $data) <p data-page-body>Corpo</p> @endcomponent',
        ['data' => $data],
    );
}

/**
 * Vite in modalità "hot" (dev server finto): gli asset si riconoscono dal percorso sorgente
 * e il test non dipende da una build.
 */
function fakeViteDevServer(): void
{
    $hot = tempnam(sys_get_temp_dir(), 'vite-hot');
    file_put_contents($hot, 'http://vite.test');
    app(Vite::class)->useHotFile($hot);
}

it('loads only the stylesheet and the x-ui bundle, not the Echo/Reverb bundle', function (): void {
    fakeViteDevServer();

    test()->get(route('home'))->assertOk()
        ->assertSee('http://vite.test/resources/css/app.css', escape: false)
        ->assertSee('http://vite.test/resources/js/ui.js', escape: false)
        ->assertDontSee('resources/js/app.js', escape: false);
});

it('renders a single toaster at the end of the body', function (): void {
    $html = (string) test()->get(route('catalog'))->getContent();

    // data-toaster esatto: gli outlet dentro dialog e drawer hanno data-toaster-outlet.
    expect(preg_match_all('/data-toaster(?![-\w])/', $html, $matches, PREG_OFFSET_CAPTURE))->toBe(1)
        ->and($matches[0][0][1])->toBeGreaterThan(strrpos($html, 'data-site-footer'));
});

it('preloads the latin subset of the text font', function (): void {
    fakeViteDevServer();

    renderPublicLayout()->assertSee('<link rel="preload" href="http://vite.test/'.FontPreload::ASSET.'" as="font" type="font/woff2" crossorigin data-font-preload>', escape: false);
});

it('skips the font preload when the Vite manifest is missing', function (): void {
    $vite = (new Vite)->useManifestFilename('missing-manifest.json');

    expect((new FontPreload($vite))->url())->toBeNull();
});

it('forces the light theme on pages that did not opt in', function (): void {
    renderPublicLayout()
        ->assertSee('data-theme-forced="light"', escape: false)
        ->assertSee('window.arkvelTheme', escape: false)
        ->assertDontSee('data-theme-option="', escape: false);
});

it('follows the chosen theme and shows the theme selectors on themeable pages', function (): void {
    $html = (string) renderPublicLayout(['themeable' => true]);

    expect($html)
        ->not->toContain('data-theme-forced')
        ->toContain('window.arkvelTheme')
        // Un selettore nel menu mobile e uno nel footer.
        ->and(substr_count($html, 'data-theme-option="dark"'))->toBe(2);
});

it('includes the theme script before the stylesheets', function (): void {
    $html = (string) renderPublicLayout();

    expect(strpos($html, 'window.arkvelTheme'))->toBeLessThan(strpos($html, 'rel="stylesheet"'));
});

it('uses the page title and description as a fallback without SEO data', function (): void {
    renderPublicLayout(['title' => 'Pagina di prova', 'metaDescription' => 'Descrizione di prova'])
        ->assertSee('<title>Pagina di prova</title>', escape: false)
        ->assertSee('<meta name="description" content="Descrizione di prova">', escape: false);

    renderPublicLayout()
        ->assertSee('<title>'.config('app.name').'</title>', escape: false)
        ->assertSee(__('public.meta.default_description'));
});

it('renders a skip link to the main region, which is the page transition region', function (): void {
    renderPublicLayout()
        ->assertSeeInOrder(['data-skip-link', 'Vai al contenuto', 'data-site-header', '<main id="main" tabindex="-1"', 'wire:transition.navigate', 'data-page-body', '</main>', 'data-site-footer'], escape: false);
});

it('announces page changes in a persistent live region', function (): void {
    renderPublicLayout()
        ->assertSee('aria-live="polite"', escape: false)
        ->assertSee('data-template="Pagina caricata: :title"', escape: false);
});

it('colors the navigation progress bar with the primary token', function (): void {
    expect(config('livewire.navigate.show_progress_bar'))->toBeTrue()
        ->and(config('livewire.navigate.progress_bar_color'))->toBe('var(--color-primary)')
        // Le altre chiavi restano quelle del pacchetto.
        ->and(config('livewire.component_layout'))->toBe('layouts::app');
});

it('renders a single breadcrumb, the one of the layout, on pages that have one', function (): void {
    $organization = Organization::factory()->verified()->create();
    $animal = Animal::factory()->available()->forOrganization($organization)->create();

    foreach ([route('animals.show', $animal), route('organizations.show', $organization)] as $url) {
        $html = (string) test()->get($url)->assertOk()->getContent();

        expect(substr_count($html, 'aria-label="'.__('seo.breadcrumb.label').'"'))->toBe(1)
            ->and(substr_count($html, 'data-breadcrumb'))->toBeGreaterThanOrEqual(1);
    }
});
