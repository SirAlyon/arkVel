<?php

declare(strict_types=1);

namespace App\Support\Media;

use App\Enums\ImageConversion;
use App\Enums\ImageFormat;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use InvalidArgumentException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\ResponsiveImages\ResponsiveImage;
use Throwable;

/**
 * Traduce un Media e una conversione (App\Enums\ImageConversion) nei dati di
 * rendering di x-ui.picture e x-ui.gallery.
 *
 * Legge solo ciò che è già stato generato (`generated_conversions` e
 * `responsive_images` del Media), quindi segue l'ambiente da solo: senza AVIF non c'è il
 * <source> AVIF. Lo srcset è costruito a mano, senza il placeholder SVG che medialibrary
 * vi aggiunge (pensato per il suo lazy loading in JS): qui il placeholder è lo sfondo
 * dell'<img>.
 *
 * Non usa MAI il file originale: può pesare diversi MB e, se la ripulitura al caricamento
 * (App\Listeners\Media\StripUploadedImageMetadata) non è riuscita, conservare i metadati
 * della foto (EXIF/GPS: la casa di chi l'ha scattata). Finché le conversioni in coda non
 * sono pronte si mostra l'anteprima `thumb`, sincrona e ricodificata da GD; senza
 * neanche quella, null (x-ui.picture mostra il blocco con la zampa).
 */
final readonly class MediaPictures
{
    /**
     * La conversione sincrona (nonQueued) registrata dal model accanto a quelle pubbliche:
     * pronta appena il caricamento termina.
     */
    public const string PREVIEW_CONVERSION = 'thumb';

    public function __construct(private FilesystemFactory $filesystems) {}

    /**
     * La foto nella conversione `$conversion` (card · hero · og) o, finché il suo JPEG non
     * è pronto, l'anteprima `thumb` con le stesse proporzioni dichiarate. Null senza
     * nessuna delle due.
     */
    public function picture(Media $media, ImageConversion $conversion): ?Picture
    {
        if (! $conversion->hasFixedDimensions()) {
            throw new InvalidArgumentException("The [{$conversion->value}] conversion has no fixed size: use zoom() instead.");
        }

        if (! $media->hasGeneratedConversion($conversion->conversionName(ImageFormat::Jpeg))) {
            return $this->preview($media, $conversion);
        }

        $src = $media->getUrl($conversion->conversionName(ImageFormat::Jpeg));
        $srcset = null;
        $sources = [];
        $placeholder = null;

        foreach ($conversion->formats() as $format) {
            $name = $conversion->conversionName($format);

            if (! $media->hasGeneratedConversion($name)) {
                continue;
            }

            $formatSrcset = $this->srcset($media, $name, $conversion->width());
            $placeholder ??= $media->responsiveImages($name)->getPlaceholderSvg();

            if ($format->isFallback()) {
                $srcset = $formatSrcset;
            } else {
                $sources[] = new PictureSource($format->mimeType(), $formatSrcset);
            }
        }

        return new Picture(
            src: $src,
            width: $conversion->width(),
            height: $conversion->height(),
            srcset: $srcset,
            sources: $sources,
            placeholder: $placeholder,
        );
    }

    /**
     * L'anteprima `thumb` con le proporzioni dichiarate di `$conversion` (la vista le
     * impone con aspect-ratio e object-cover), senza srcset né placeholder. Null finché
     * la thumb non esiste.
     */
    public function preview(Media $media, ImageConversion $conversion): ?Picture
    {
        if (! $media->hasGeneratedConversion(self::PREVIEW_CONVERSION)) {
            return null;
        }

        return new Picture(
            src: $media->getUrl(self::PREVIEW_CONVERSION),
            width: $conversion->width(),
            height: $conversion->height(),
            preview: true,
        );
    }

    /**
     * La foto intera per la lightbox, nel primo formato generato. Null finché la
     * conversione `zoom` non è pronta: le dimensioni reali si leggono dai nomi delle sue
     * responsive images e senza quelle PhotoSwipe non può impaginare la foto.
     */
    public function zoom(Media $media): ?ZoomImage
    {
        $conversion = ImageConversion::Zoom;

        foreach ($conversion->formats() as $format) {
            $name = $conversion->conversionName($format);
            $files = $this->responsiveFiles($media, $name);

            if (! $media->hasGeneratedConversion($name) || $files === []) {
                continue;
            }

            $largest = $files[array_key_last($files)];

            return new ZoomImage(
                src: $media->getUrl($name),
                width: $largest->width(),
                height: $largest->height(),
                srcset: $this->implodeSrcset($files),
            );
        }

        return null;
    }

    /**
     * Cosa apre la lightbox (e il link della foto senza JS): `zoom`, altrimenti `hero` o
     * `card` (dimensioni fisse, esatte perché ritagliate), altrimenti la `thumb` con le
     * dimensioni lette dal file. Sempre una conversione, mai l'originale; null se non ce
     * n'è nessuna.
     */
    public function lightbox(Media $media): ?ZoomImage
    {
        return $this->zoom($media)
            ?? $this->fixedLightbox($media, ImageConversion::Hero)
            ?? $this->fixedLightbox($media, ImageConversion::Card)
            ?? $this->previewLightbox($media);
    }

    private function fixedLightbox(Media $media, ImageConversion $conversion): ?ZoomImage
    {
        $picture = $this->picture($media, $conversion);

        if ($picture === null || $picture->preview) {
            return null;
        }

        return new ZoomImage($picture->src, $picture->width, $picture->height, $picture->srcset);
    }

    /**
     * La thumb ha le proporzioni della foto (non è ritagliata), quindi le sue dimensioni
     * si leggono dal file: è piccola (≤ 400 px) e serve solo finché le conversioni in
     * coda non sono pronte.
     */
    private function previewLightbox(Media $media): ?ZoomImage
    {
        if (! $media->hasGeneratedConversion(self::PREVIEW_CONVERSION)) {
            return null;
        }

        try {
            $contents = $this->filesystems
                ->disk($media->conversions_disk ?: $media->disk)
                ->get($media->getPathRelativeToRoot(self::PREVIEW_CONVERSION));
            $size = is_string($contents) && $contents !== '' ? getimagesizefromstring($contents) : false;
        } catch (Throwable) {
            $size = false;
        }

        if ($size === false || $size[0] < 1 || $size[1] < 1) {
            return null;
        }

        return new ZoomImage($media->getUrl(self::PREVIEW_CONVERSION), $size[0], $size[1]);
    }

    private function srcset(Media $media, string $name, int $width): string
    {
        $files = $this->responsiveFiles($media, $name);

        return $files === [] ? $media->getUrl($name).' '.$width.'w' : $this->implodeSrcset($files);
    }

    /**
     * @return list<ResponsiveImage>
     */
    private function responsiveFiles(Media $media, string $name): array
    {
        /** @var list<ResponsiveImage> $files */
        $files = $media->responsiveImages($name)->files
            ->sortBy(static fn (ResponsiveImage $image): int => $image->width())
            ->values()
            ->all();

        return $files;
    }

    /**
     * @param  list<ResponsiveImage>  $files
     */
    private function implodeSrcset(array $files): string
    {
        return implode(', ', array_map(
            static fn (ResponsiveImage $image): string => $image->url().' '.$image->width().'w',
            $files,
        ));
    }
}
