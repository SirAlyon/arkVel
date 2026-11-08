<?php

declare(strict_types=1);

namespace App\Support\Media;

use InvalidArgumentException;

/**
 * Dati di rendering di un'immagine responsive (x-ui.picture): l'<img> di fallback con
 * `src`/`srcset`, i <source> dei formati moderni, le dimensioni dichiarate (proporzioni
 * stabili, nessun CLS) e il placeholder sfocato (data URI) da usare come sfondo finché
 * la foto carica.
 *
 * `preview`: l'<img> è un'anteprima provvisoria (la `thumb` sincrona, finché le
 * conversioni in coda non sono pronte) con proporzioni diverse da width/height. La vista
 * le impone allora con un aspect-ratio esplicito e object-cover: nessun CLS quando il file
 * arriva. Mai l'originale, che può contenere i metadati della foto (EXIF/GPS).
 *
 * Da un Media lo costruisce App\Support\Media\MediaPictures; da URL statici fromUrl().
 */
final readonly class Picture
{
    /**
     * @param  list<PictureSource>  $sources
     */
    public function __construct(
        public string $src,
        public int $width,
        public int $height,
        public ?string $srcset = null,
        public array $sources = [],
        public ?string $placeholder = null,
        public bool $preview = false,
    ) {
        if ($width < 1 || $height < 1) {
            throw new InvalidArgumentException('A picture needs a positive width and height.');
        }

        // Finisce in un url('…') inline: solo un'immagine incorporata, mai un URL arbitrario.
        if ($placeholder !== null && ! str_starts_with($placeholder, 'data:image/')) {
            throw new InvalidArgumentException('A picture placeholder must be a data:image/ URI.');
        }
    }

    /**
     * @param  iterable<PictureSource|array{type: string, srcset: string}>  $sources
     */
    public static function fromUrl(
        string $src,
        int $width,
        int $height,
        ?string $srcset = null,
        iterable $sources = [],
        ?string $placeholder = null,
    ): self {
        $normalized = [];

        foreach ($sources as $source) {
            $normalized[] = $source instanceof PictureSource
                ? $source
                : new PictureSource($source['type'], $source['srcset']);
        }

        return new self($src, $width, $height, $srcset, $normalized, $placeholder);
    }
}
