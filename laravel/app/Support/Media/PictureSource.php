<?php

declare(strict_types=1);

namespace App\Support\Media;

/**
 * Un <source> di x-ui.picture: un formato moderno (AVIF, WebP) con il suo srcset.
 */
final readonly class PictureSource
{
    public function __construct(
        public string $type,
        public string $srcset,
    ) {}
}
