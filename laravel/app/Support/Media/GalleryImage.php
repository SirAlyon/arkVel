<?php

declare(strict_types=1);

namespace App\Support\Media;

use InvalidArgumentException;

/**
 * Una foto di x-ui.gallery: la slide (hero 3:2), la miniatura, la foto per la lightbox
 * (e per il link senza JS) e il suo testo alternativo, uno per foto.
 *
 * `zoom` null vuol dire «apri la slide stessa»: va bene per una slide vera, non per
 * un'anteprima provvisoria (le sue dimensioni dichiarate non sono quelle del file), che
 * quindi deve portare la sua foto per la lightbox.
 */
final readonly class GalleryImage
{
    public function __construct(
        public Picture $slide,
        public Picture $thumb,
        public string $alt,
        public ?ZoomImage $zoom = null,
    ) {
        if ($zoom === null && $slide->preview) {
            throw new InvalidArgumentException('A gallery image with a preview slide needs its own lightbox image.');
        }
    }

    /**
     * Cosa apre la lightbox: la foto intera o, in sua assenza, la slide stessa.
     */
    public function zoomOrSlide(): ZoomImage
    {
        return $this->zoom ?? new ZoomImage(
            src: $this->slide->src,
            width: $this->slide->width,
            height: $this->slide->height,
            srcset: $this->slide->srcset,
        );
    }
}
