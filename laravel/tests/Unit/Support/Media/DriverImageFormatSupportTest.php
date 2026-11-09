<?php

declare(strict_types=1);

use App\Enums\ImageFormat;
use App\Support\Media\DriverImageFormatSupport;
use App\Support\Media\ImageFormatSupport;

it('always supports the JPEG fallback, whatever the driver', function (string $driver): void {
    expect((new DriverImageFormatSupport($driver))->supports(ImageFormat::Jpeg))->toBeTrue();
})->with(['gd', 'imagick', 'vips']);

it('reads WebP and AVIF support from the GD build', function (): void {
    $support = new DriverImageFormatSupport('gd');

    expect($support->supports(ImageFormat::Webp))->toBe((imagetypes() & IMG_WEBP) !== 0)
        ->and($support->supports(ImageFormat::Avif))->toBe((imagetypes() & IMG_AVIF) !== 0);
});

it('reads WebP and AVIF support from ImageMagick, and none without the extension', function (): void {
    $support = new DriverImageFormatSupport('imagick');
    $imagick = class_exists(Imagick::class);

    expect($support->supports(ImageFormat::Webp))->toBe($imagick && Imagick::queryFormats('WEBP') !== [])
        ->and($support->supports(ImageFormat::Avif))->toBe($imagick && Imagick::queryFormats('AVIF') !== []);
});

it('falls back to JPEG only with an unknown driver', function (): void {
    $support = new DriverImageFormatSupport('vips');

    expect($support->supports(ImageFormat::Webp))->toBeFalse()
        ->and($support->supports(ImageFormat::Avif))->toBeFalse();
});

it('is bound to the medialibrary image driver', function (): void {
    config()->set('media-library.image_driver', 'vips');

    expect(app(ImageFormatSupport::class))->toBeInstanceOf(DriverImageFormatSupport::class)
        ->and(app(ImageFormatSupport::class)->supports(ImageFormat::Webp))->toBeFalse();
});
