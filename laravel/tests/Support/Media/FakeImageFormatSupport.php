<?php

declare(strict_types=1);

namespace Tests\Support\Media;

use App\Enums\ImageFormat;
use App\Support\Media\ImageFormatSupport;

/**
 * Supporto dei formati deciso dal test: per provare ogni combinazione di AVIF/WebP
 * indipendentemente dal GD del container.
 */
final readonly class FakeImageFormatSupport implements ImageFormatSupport
{
    /**
     * @param  list<ImageFormat>  $formats
     */
    public function __construct(private array $formats) {}

    public static function bind(ImageFormat ...$formats): void
    {
        app()->instance(ImageFormatSupport::class, new self(array_values($formats)));
    }

    public function supports(ImageFormat $format): bool
    {
        return $format->isFallback() || in_array($format, $this->formats, true);
    }
}
