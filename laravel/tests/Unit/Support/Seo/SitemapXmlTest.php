<?php

declare(strict_types=1);

use App\Support\Seo\RobotsTxt;
use App\Support\Seo\Sitemap\SitemapUrl;
use App\Support\Seo\Sitemap\SitemapXml;
use Carbon\CarbonImmutable;

it('renders a valid sitemaps.org document with escaped URLs and W3C dates', function (): void {
    $xml = (new SitemapXml)->render([
        new SitemapUrl('https://arkvel.test/'),
        new SitemapUrl('https://arkvel.test/animali?a=1&b=2', CarbonImmutable::parse('2026-11-08 10:30:00', 'UTC')),
    ]);

    $document = simplexml_load_string($xml);

    expect($xml)->toStartWith('<?xml version="1.0" encoding="UTF-8"?>')
        ->toContain('<loc>https://arkvel.test/animali?a=1&amp;b=2</loc>')
        ->and($document)->not->toBeFalse()
        ->and($document->getNamespaces())->toBe(['' => SitemapXml::NAMESPACE])
        ->and($document->url)->toHaveCount(2)
        ->and(isset($document->url[0]->lastmod))->toBeFalse()
        ->and((string) $document->url[1]->lastmod)->toBe('2026-11-08T10:30:00+00:00');
});

it('renders an empty urlset', function (): void {
    expect(simplexml_load_string((new SitemapXml)->render([])))->not->toBeFalse();
});

it('blocks the private areas and points to the absolute sitemap URL', function (): void {
    expect(app(RobotsTxt::class)->render())->toBe(implode("\n", [
        'User-agent: *',
        'Disallow: /account',
        'Disallow: /manage',
        'Disallow: /admin',
        '',
        'Sitemap: '.route('seo.sitemap'),
    ])."\n")
        ->and(route('seo.sitemap'))->toStartWith('http');
});
