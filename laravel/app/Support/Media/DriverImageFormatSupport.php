<?php

declare(strict_types=1);

namespace App\Support\Media;

use App\Enums\ImageFormat;
use Illuminate\Container\Attributes\Config;
use Imagick;

/**
 * Supporto dei formati letto dal driver configurato in `media-library.image_driver`.
 *
 * - `gd`: i bit di imagetypes() (lettura e scrittura insieme: GD li accende solo se la
 *   libreria c'è, cioè con `--with-webp` / `--with-avif` nel Dockerfile);
 * - `imagick`: i formati di ImageMagick (queryFormats);
 * - altri driver: solo JPEG, il fallback sicuro.
 */
final readonly class DriverImageFormatSupport implements ImageFormatSupport
{
    public function __construct(
        #[Config('media-library.image_driver', 'gd')]
        private string $driver,
    ) {}

    public function supports(ImageFormat $format): bool
    {
        if ($format->isFallback()) {
            return true;
        }

        return match ($this->driver) {
            'gd' => $this->gdSupports($format),
            'imagick' => $this->imagickSupports($format),
            default => false,
        };
    }

    private function gdSupports(ImageFormat $format): bool
    {
        if (! function_exists('imagetypes')) {
            return false;
        }

        $flag = match ($format) {
            ImageFormat::Avif => IMG_AVIF,
            ImageFormat::Webp => IMG_WEBP,
            ImageFormat::Jpeg => IMG_JPG,
        };

        return (imagetypes() & $flag) !== 0;
    }

    private function imagickSupports(ImageFormat $format): bool
    {
        return class_exists(Imagick::class)
            && Imagick::queryFormats(strtoupper($format->value)) !== [];
    }
}
