<?php

declare(strict_types=1);

namespace App\Support\Media;

use App\Enums\ImageConversion;
use Illuminate\Container\Attributes\Config;
use Illuminate\Contracts\Translation\Translator;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Costruisce le foto di x-ui.gallery dai Media di una collection.
 *
 * - slide `hero`, o la sua anteprima `thumb` finché la conversione è in coda;
 * - miniatura: la `thumb` (≤ 400 px, senza placeholder) per i bottoni da 4 rem;
 * - lightbox: zoom → hero → card → thumb (App\Support\Media\MediaPictures::lightbox()),
 *   mai l'originale. Un media senza nessuna conversione è saltato.
 *
 * Il testo alternativo di ogni foto è la custom property `alt` del Media (una stringa o
 * una mappa lingua → testo, come i campi translatable), con fallback «Foto n di N di
 * <nome>»: mai lo stesso alt per tutte le foto.
 */
final readonly class GalleryImages
{
    public function __construct(
        private MediaPictures $pictures,
        private Translator $translator,
        #[Config('app.fallback_locale')]
        private ?string $fallbackLocale = null,
    ) {}

    /**
     * @param  iterable<Media>  $media
     * @return list<GalleryImage>
     */
    public function fromMedia(iterable $media, string $name): array
    {
        $photos = [];

        foreach ($media as $item) {
            $slide = $this->pictures->picture($item, ImageConversion::Hero);
            $zoom = $this->pictures->lightbox($item);

            if ($slide === null || $zoom === null) {
                continue;
            }

            $photos[] = [$item, $slide, $zoom];
        }

        $total = count($photos);
        $images = [];

        foreach ($photos as $index => [$item, $slide, $zoom]) {
            $images[] = new GalleryImage(
                slide: $slide,
                thumb: $this->pictures->preview($item, ImageConversion::Card) ?? $slide,
                alt: $this->alt($item) ?? $this->fallbackAlt($index + 1, $total, $name),
                zoom: $zoom,
            );
        }

        return $images;
    }

    private function fallbackAlt(int $position, int $total, string $name): string
    {
        $alt = $this->translator->get('ui_media.gallery.photo_alt', [
            'position' => $position,
            'total' => $total,
            'name' => $name,
        ]);

        return is_string($alt) ? $alt : $name;
    }

    private function alt(Media $media): ?string
    {
        $alt = $media->getCustomProperty('alt');

        if (is_array($alt)) {
            $alt = $alt[$this->translator->getLocale()] ?? ($this->fallbackLocale !== null ? $alt[$this->fallbackLocale] ?? null : null);
        }

        return is_string($alt) && trim($alt) !== '' ? trim($alt) : null;
    }
}
