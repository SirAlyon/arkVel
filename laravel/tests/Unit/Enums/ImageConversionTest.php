<?php

declare(strict_types=1);

use App\Enums\ImageConversion;
use App\Enums\ImageFormat;
use Spatie\Image\Enums\Fit;

it('declares the size and fit of each conversion', function (ImageConversion $conversion, int $width, int $height, Fit $fit, bool $fixed): void {
    expect($conversion->width())->toBe($width)
        ->and($conversion->height())->toBe($height)
        ->and($conversion->fit())->toBe($fit)
        ->and($conversion->hasFixedDimensions())->toBe($fixed);
})->with([
    'card 4:5' => [ImageConversion::Card, 800, 1000, Fit::Crop, true],
    'hero 3:2' => [ImageConversion::Hero, 1536, 1024, Fit::Crop, true],
    'og' => [ImageConversion::Og, 1200, 630, Fit::Crop, true],
    'zoom, uncropped' => [ImageConversion::Zoom, 2048, 2048, Fit::Max, false],
]);

it('keeps the card at 4:5 and the hero at 3:2', function (): void {
    expect(ImageConversion::Card->width() * 5)->toBe(ImageConversion::Card->height() * 4)
        ->and(ImageConversion::Hero->width() * 2)->toBe(ImageConversion::Hero->height() * 3);
});

it('generates responsive images for everything but the Open Graph image', function (ImageConversion $conversion, bool $responsive): void {
    expect($conversion->hasResponsiveImages())->toBe($responsive);
})->with([
    [ImageConversion::Card, true],
    [ImageConversion::Hero, true],
    [ImageConversion::Og, false],
    [ImageConversion::Zoom, true],
]);

it('offers AVIF and WebP sources only where a <picture> uses them', function (ImageConversion $conversion, array $formats, bool $sources): void {
    expect($conversion->formats())->toBe($formats)
        ->and($conversion->hasPictureSources())->toBe($sources);
})->with([
    'card' => [ImageConversion::Card, [ImageFormat::Avif, ImageFormat::Webp, ImageFormat::Jpeg], true],
    'hero' => [ImageConversion::Hero, [ImageFormat::Avif, ImageFormat::Webp, ImageFormat::Jpeg], true],
    'og: JPEG for every crawler' => [ImageConversion::Og, [ImageFormat::Jpeg], false],
    'zoom: one format for PhotoSwipe' => [ImageConversion::Zoom, [ImageFormat::Webp, ImageFormat::Jpeg], false],
]);

it('names the JPEG conversion after the case and the others after case and format', function (): void {
    expect(ImageConversion::Card->conversionName(ImageFormat::Jpeg))->toBe('card')
        ->and(ImageConversion::Card->conversionName(ImageFormat::Webp))->toBe('card-webp')
        ->and(ImageConversion::Hero->conversionName(ImageFormat::Avif))->toBe('hero-avif')
        ->and(ImageConversion::Og->conversionName(ImageFormat::Jpeg))->toBe('og');
});
