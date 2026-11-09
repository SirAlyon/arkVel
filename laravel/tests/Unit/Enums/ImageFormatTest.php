<?php

declare(strict_types=1);

use App\Enums\ImageFormat;

it('maps each format to its MIME type', function (ImageFormat $format, string $mime): void {
    expect($format->mimeType())->toBe($mime);
})->with([
    'avif' => [ImageFormat::Avif, 'image/avif'],
    'webp' => [ImageFormat::Webp, 'image/webp'],
    'jpeg' => [ImageFormat::Jpeg, 'image/jpeg'],
]);

it('encodes modern formats at a lower quality than the JPEG fallback', function (): void {
    expect(ImageFormat::Avif->quality())->toBe(55)
        ->and(ImageFormat::Webp->quality())->toBe(78)
        ->and(ImageFormat::Jpeg->quality())->toBe(82);
});

it('lists the formats in order of preference, with JPEG as the only fallback', function (): void {
    expect(ImageFormat::cases())->toBe([ImageFormat::Avif, ImageFormat::Webp, ImageFormat::Jpeg])
        ->and(ImageFormat::Jpeg->isFallback())->toBeTrue()
        ->and(ImageFormat::Avif->isFallback())->toBeFalse()
        ->and(ImageFormat::Webp->isFallback())->toBeFalse();
});
