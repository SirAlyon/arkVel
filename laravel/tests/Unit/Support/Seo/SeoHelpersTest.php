<?php

declare(strict_types=1);

use App\Support\Seo\Breadcrumb;
use App\Support\Seo\JsonLd;
use App\Support\Seo\OpenGraphLocale;
use App\Support\Seo\SeoText;

describe('JsonLd', function (): void {
    it('cannot be broken out of its script tag', function (): void {
        $json = JsonLd::encode(['name' => '</script><script>alert(1)</script> & co']);

        expect($json)->not->toContain('<')
            ->not->toContain('>')
            ->not->toContain('&')
            ->and(json_decode($json, true, flags: JSON_THROW_ON_ERROR))->toBe(['name' => '</script><script>alert(1)</script> & co']);
    });

    it('keeps URLs and accents readable', function (): void {
        expect(JsonLd::encode(['url' => 'https://arkvel.test/enti/città']))->toBe('{"url":"https://arkvel.test/enti/città"}');
    });

    it('survives invalid UTF-8 in user content', function (): void {
        expect(JsonLd::encode(['name' => "Argo\xB1"]))->toBe('{"name":"Argo�"}');
    });
});

describe('OpenGraphLocale', function (): void {
    it('maps every app locale, falling back to Italian', function (string $locale, string $expected): void {
        expect(OpenGraphLocale::for($locale))->toBe($expected);
    })->with([
        ['it', 'it_IT'],
        ['en', 'en_GB'],
        ['fr', 'it_IT'],
    ]);
});

describe('SeoText', function (): void {
    it('strips markup and collapses whitespace', function (): void {
        expect(SeoText::excerpt("<p>Argo è\n\n un <strong>cane</strong>   dolcissimo.</p>"))->toBe('Argo è un cane dolcissimo.');
    });

    it('turns the RichEditor HTML into plain text: entities decoded, blocks spaced', function (): void {
        $html = '<p>Argo &amp; Luna</p><p>Due&nbsp;cani&nbsp;&nbsp;dolcissimi,<br>l&#39;uno con l&rsquo;altro.</p><ul><li>Uno</li><li>Due</li></ul>';

        expect(SeoText::excerpt($html))->toBe('Argo & Luna Due cani dolcissimi, l\'uno con l’altro. Uno Due');
    });

    it('keeps inline markup inside words and escaped tags as text', function (): void {
        expect(SeoText::excerpt('<p>Super<strong>cane</strong> &lt;3</p>'))->toBe('Supercane <3');
    });

    it('cuts long texts on a whole word', function (): void {
        expect(SeoText::excerpt('Argo ama correre nei prati', 15))->toBe('Argo ama…');
    });

    it('returns null for empty texts', function (?string $text): void {
        expect(SeoText::excerpt($text))->toBeNull();
    })->with([null, '', "  \n ", '<p> </p>', '<p>&nbsp;</p><br>']);
});

describe('Breadcrumb', function (): void {
    it('exports name and url for the component', function (): void {
        expect((new Breadcrumb('Enti', 'https://arkvel.test/enti'))->toArray())
            ->toBe(['name' => 'Enti', 'url' => 'https://arkvel.test/enti']);
    });
});
