<?php

declare(strict_types=1);

use App\Support\Seo\Breadcrumb;
use App\Support\Seo\OpenGraphType;
use App\Support\Seo\SeoData;

function seoData(mixed ...$overrides): SeoData
{
    return new SeoData(...[
        'title' => 'Argo — ArkVel',
        'description' => 'Un cane dolcissimo.',
        'canonical' => 'https://arkvel.test/animali/1',
        'locale' => 'it',
        ...$overrides,
    ]);
}

it('is indexable by default and noindex, follow on request', function (): void {
    expect(seoData()->robots())->toBeNull()
        ->and(seoData(noindex: true)->robots())->toBe('noindex, follow');
});

it('uses a large Twitter card only when there is an image', function (): void {
    expect(seoData()->twitterCard())->toBe('summary')
        ->and(seoData(image: 'https://arkvel.test/storage/1/conversions/argo-thumb.jpg')->twitterCard())->toBe('summary_large_image');
});

it('maps its locale to og:locale', function (): void {
    expect(seoData()->openGraphLocale())->toBe('it_IT')
        ->and(seoData(locale: 'en')->openGraphLocale())->toBe('en_GB');
});

it('defaults to a website with no breadcrumbs nor structured data', function (): void {
    $seo = seoData();

    expect($seo->type)->toBe(OpenGraphType::Website)
        ->and($seo->hasBreadcrumbs())->toBeFalse()
        ->and($seo->jsonLdScript())->toBeNull()
        ->and(seoData(breadcrumbs: [new Breadcrumb('Home', 'https://arkvel.test')])->hasBreadcrumbs())->toBeTrue();
});

it('wraps its schema.org nodes in a single @graph', function (): void {
    $script = seoData(jsonLd: [['@type' => 'WebSite'], ['@type' => 'BreadcrumbList']])->jsonLdScript();

    expect(json_decode((string) $script, true, flags: JSON_THROW_ON_ERROR))->toBe([
        '@context' => 'https://schema.org',
        '@graph' => [['@type' => 'WebSite'], ['@type' => 'BreadcrumbList']],
    ]);
});

it('rejects relative canonical and image URLs', function (array $overrides): void {
    seoData(...$overrides);
})->with([
    'canonical' => [['canonical' => '/animali/1']],
    'image' => [['image' => 'storage/argo.jpg']],
])->throws(InvalidArgumentException::class);
