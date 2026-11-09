<?php

declare(strict_types=1);

namespace App\Support\Media;

use App\Enums\ImageConversion;
use App\Enums\ImageFormat;
use Spatie\MediaLibrary\HasMedia;

/**
 * Registra sulle media collection di un model le conversioni delle foto pubbliche
 * (App\Enums\ImageConversion), una per formato supportato dal driver immagine.
 *
 * Tutte in coda (`queued()`, esplicito: non dipende da
 * `media-library.queue_conversions_by_default`), con responsive images e placeholder
 * sfocato dove servono. Da chiamare in registerMediaConversions():
 *
 *     resolve(ImageConversions::class)->register($this, ['photos', 'avatar']);
 *     resolve(ImageConversions::class)->register($this, ['logo'], ImageConversion::Card, ImageConversion::Og);
 *
 * Ordine di registrazione = ordine di esecuzione nel PerformConversionsJob, che si ferma
 * al primo errore: prima tutti i JPEG (il fallback che x-ui.picture aspetta per lasciare
 * l'anteprima), poi WebP, poi AVIF (la codifica più lenta e più fragile). Se AVIF fallisce,
 * il resto è già generato e la foto si vede comunque.
 */
final readonly class ImageConversions
{
    public function __construct(private ImageFormatSupport $support) {}

    /**
     * @param  list<string>  $collections
     * @param  ImageConversion  ...$presets  le conversioni da registrare (nessuna = tutte)
     */
    public function register(HasMedia $model, array $collections, ImageConversion ...$presets): void
    {
        foreach ($this->plan($presets === [] ? ImageConversion::cases() : array_values($presets)) as [$conversion, $format]) {
            $model->addMediaConversion($conversion->conversionName($format))
                ->performOnCollections(...$collections)
                ->withResponsiveImages($conversion->hasResponsiveImages())
                ->queued()
                ->fit($conversion->fit(), $conversion->width(), $conversion->height())
                ->format($format->value)
                ->quality($format->quality());
        }
    }

    /**
     * I formati generati per `$conversion` in questo ambiente, in ordine di preferenza
     * (quello dei <source>): tutti i supportati per le conversioni con <source>,
     * altrimenti il primo supportato (JPEG lo è sempre).
     *
     * @return list<ImageFormat>
     */
    public function formatsFor(ImageConversion $conversion): array
    {
        $supported = array_values(array_filter(
            $conversion->formats(),
            $this->support->supports(...),
        ));

        return $conversion->hasPictureSources() ? $supported : array_slice($supported, 0, 1);
    }

    /**
     * Le coppie conversione/formato in ordine di generazione: dal formato meno preferito
     * (JPEG, sempre leggibile) al più preferito (AVIF); a parità di formato, nell'ordine
     * dei preset. usort è stabile.
     *
     * @param  list<ImageConversion>  $presets
     * @return list<array{ImageConversion, ImageFormat}>
     */
    private function plan(array $presets): array
    {
        $pairs = [];

        foreach ($presets as $conversion) {
            foreach ($this->formatsFor($conversion) as $format) {
                $pairs[] = [$conversion, $format];
            }
        }

        $preference = array_flip(array_map(
            static fn (ImageFormat $format): string => $format->value,
            ImageFormat::cases(),
        ));

        usort($pairs, static fn (array $a, array $b): int => $preference[$b[1]->value] <=> $preference[$a[1]->value]);

        return $pairs;
    }
}
