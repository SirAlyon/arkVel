<?php

declare(strict_types=1);

use App\Support\Seo\Breadcrumb;
use App\Support\Seo\SeoData;

/*
 | partials/seo/head e partials/seo/breadcrumbs resi da soli (come li include il
 | layout pubblico) e il componente x-ui.breadcrumb.
 */

function partialSeo(mixed ...$overrides): SeoData
{
    return new SeoData(...[
        'title' => 'Argo & co — ArkVel',
        'description' => 'Un "cane" dolcissimo.',
        'canonical' => 'https://arkvel.test/animali/1',
        'locale' => 'it',
        ...$overrides,
    ]);
}

describe('head partial', function (): void {
    it('renders nothing without $seo', function (): void {
        expect(trim((string) $this->view('partials.seo.head')))->toBe('')
            ->and(trim((string) $this->view('partials.seo.head', ['seo' => 'not seo data'])))->toBe('');
    });

    it('escapes every value', function (): void {
        $this->view('partials.seo.head', ['seo' => partialSeo()])
            ->assertSee('<title>Argo &amp; co — ArkVel</title>', escape: false)
            ->assertSee('<meta name="description" content="Un &quot;cane&quot; dolcissimo.">', escape: false)
            ->assertSee('<meta property="og:title" content="Argo &amp; co — ArkVel">', escape: false);
    });

    it('marks noindex pages and image cards', function (): void {
        $this->view('partials.seo.head', ['seo' => partialSeo(noindex: true, image: 'https://arkvel.test/argo.jpg', imageAlt: 'Foto di Argo', locale: 'en')])
            ->assertSee('<meta name="robots" content="noindex, follow">', escape: false)
            ->assertSee('<meta property="og:image" content="https://arkvel.test/argo.jpg">', escape: false)
            ->assertSee('<meta property="og:image:alt" content="Foto di Argo">', escape: false)
            ->assertSee('<meta property="og:locale" content="en_GB">', escape: false)
            ->assertSee('<meta name="twitter:card" content="summary_large_image">', escape: false)
            ->assertDontSee('article:modified_time', escape: false)
            ->assertDontSee('hreflang', escape: false);
    });
});

describe('breadcrumbs partial', function (): void {
    it('renders nothing without $seo, and no trail on pages without breadcrumbs', function (): void {
        expect(trim((string) $this->view('partials.seo.breadcrumbs')))->toBe('')
            ->and(trim((string) $this->view('partials.seo.breadcrumbs', ['seo' => partialSeo()])))->toBe('');
    });

    it('renders the trail and the page structured data', function (): void {
        $seo = partialSeo(
            breadcrumbs: [new Breadcrumb('Home', 'https://arkvel.test'), new Breadcrumb('Argo', 'https://arkvel.test/animali/1')],
            jsonLd: [['@type' => 'BreadcrumbList']],
        );

        $this->view('partials.seo.breadcrumbs', ['seo' => $seo])
            ->assertSee('data-breadcrumb', escape: false)
            ->assertSee('<script type="application/ld+json">{"@context":"https://schema.org","@graph":[{"@type":"BreadcrumbList"}]}</script>', escape: false);
    });
});

describe('x-ui.breadcrumb', function (): void {
    it('is a named nav with an ordered list and the current page last', function (): void {
        $this->blade('<x-ui.breadcrumb :items="$items" />', ['items' => [
            ['name' => 'Home', 'url' => 'https://arkvel.test'],
            ['name' => 'Catalogo', 'url' => 'https://arkvel.test/animali'],
            ['name' => 'Argo', 'url' => 'https://arkvel.test/animali/1'],
        ]])
            ->assertSee('<nav aria-label="Percorso di navigazione"', escape: false)
            ->assertSeeInOrder(['<ol', 'href="https://arkvel.test"', 'href="https://arkvel.test/animali"', 'aria-current="page"', 'Argo', '</ol>'], escape: false)
            ->assertDontSee('href="https://arkvel.test/animali/1"', escape: false)
            ->assertSee('wire:navigate.hover', escape: false)
            ->assertSee('data-text-link', escape: false)
            ->assertSee('min-h-6 min-w-6', escape: false);
    });

    it('draws decorative separators between items only', function (): void {
        $view = $this->blade('<x-ui.breadcrumb :items="$items" />', ['items' => [
            ['name' => 'Home', 'url' => 'https://arkvel.test'],
            ['name' => 'Enti', 'url' => 'https://arkvel.test/enti'],
            ['name' => 'Rifugio', 'url' => 'https://arkvel.test/enti/rifugio'],
        ]]);

        $html = (string) $view;

        expect(substr_count($html, 'data-icon="chevron-right"'))->toBe(2)
            ->and(preg_match_all('/<svg[^>]*data-icon="chevron-right"[^>]*>/', $html, $icons))->toBe(2)
            ->and(collect($icons[0])->every(static fn (string $svg): bool => str_contains($svg, 'aria-hidden="true"')))->toBeTrue();
    });

    it('renders nothing without items', function (): void {
        expect(trim((string) $this->blade('<x-ui.breadcrumb />')))->toBe('');
    });

    it('is translated', function (): void {
        app()->setLocale('en');

        $this->blade('<x-ui.breadcrumb :items="[[\'name\' => \'Home\', \'url\' => \'https://arkvel.test\']]" />')
            ->assertSee('<nav aria-label="Breadcrumb"', escape: false);
    });

    it('caps the intermediate items only below md, where the space is short', function (): void {
        $html = (string) $this->blade('<x-ui.breadcrumb :items="$items" />', ['items' => [
            ['name' => 'Home', 'url' => 'https://arkvel.test'],
            ['name' => 'Associazione Amici degli Animali della Valle', 'url' => 'https://arkvel.test/enti/amici'],
            ['name' => 'Argo', 'url' => 'https://arkvel.test/animali/1'],
        ]]);

        expect($html)->toContain('max-md:max-w-crumb')
            ->and(preg_match('/(?<![:\w-])max-w-crumb/', $html))->toBe(0)
            ->and(substr_count($html, 'max-md:max-w-crumb'))->toBe(1);
    });
});
