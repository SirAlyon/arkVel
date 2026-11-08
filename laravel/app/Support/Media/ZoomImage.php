<?php

declare(strict_types=1);

namespace App\Support\Media;

/**
 * La foto intera per la lightbox (PhotoSwipe): URL, srcset e dimensioni reali, che
 * PhotoSwipe deve conoscere prima di caricarla.
 */
final readonly class ZoomImage
{
    public function __construct(
        public string $src,
        public int $width,
        public int $height,
        public ?string $srcset = null,
    ) {}
}
