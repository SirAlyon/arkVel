<?php

declare(strict_types=1);

namespace App\Enums;

use Spatie\Image\Enums\Fit;

/**
 * Conversioni medialibrary delle foto pubbliche (UI-4), registrate da
 * App\Support\Media\ImageConversions e lette da x-ui.picture / x-ui.gallery.
 *
 * - `card` 4:5 (800×1000): card del catalogo e miniature della galleria;
 * - `hero` 3:2 (1536×1024): foto principale e slide della scheda animale. 3:2 e non 16:9
 *   perché è il rapporto nativo di molte fotocamere, taglia meno un soggetto (un animale,
 *   spesso in verticale) e su mobile resta alta abbastanza da fare da LCP (16:9 a 375 px
 *   sono 211 px);
 * - `og` 1200×630: anteprima Open Graph, solo JPEG (i crawler dei social non leggono
 *   tutti WebP/AVIF) e senza responsive images;
 * - `zoom`: la foto intera (nessun ritaglio) entro 2048 px per la lightbox, in un solo
 *   formato perché PhotoSwipe usa un <img> semplice.
 *
 * Il nome di una conversione è il valore del case per il JPEG e `<valore>-<formato>` per
 * i formati moderni (`card`, `card-webp`, `card-avif`): così `card` resta un URL valido
 * ovunque, anche senza <picture>.
 */
enum ImageConversion: string
{
    case Card = 'card';
    case Hero = 'hero';
    case Og = 'og';
    case Zoom = 'zoom';

    public function width(): int
    {
        return match ($this) {
            self::Card => 800,
            self::Hero => 1536,
            self::Og => 1200,
            self::Zoom => 2048,
        };
    }

    public function height(): int
    {
        return match ($this) {
            self::Card => 1000,
            self::Hero => 1024,
            self::Og => 630,
            self::Zoom => 2048,
        };
    }

    /**
     * Crop ritaglia esattamente alle dimensioni (proporzioni fisse, nessun CLS);
     * Max ridimensiona dentro il riquadro senza ritagliare né ingrandire.
     *
     * Crop ingrandisce le foto più piccole del riquadro (spatie/image non ha un Crop senza
     * upsize): è voluto. card/hero/og devono avere esattamente le dimensioni dichiarate
     * (width/height dell'<img>, data-pswp-* della lightbox, 1200×630 per i social) e un
     * Crop senza upsize darebbe file di proporzioni diverse a seconda della foto. Il costo
     * è solo di byte: le responsive images si generano solo a larghezze minori e il
     * browser avrebbe ingrandito comunque. Il rimedio vero è una risoluzione minima nel
     * form di caricamento (Filament, fuori da UI-4). thumb e zoom usano Max: non ingrandiscono.
     */
    public function fit(): Fit
    {
        return $this === self::Zoom ? Fit::Max : Fit::Crop;
    }

    /**
     * Dimensioni note a priori (le stesse per ogni foto): `width`/`height` dell'<img>
     * vengono da qui. Per `zoom` dipendono dalla foto e si leggono dai file generati.
     */
    public function hasFixedDimensions(): bool
    {
        return $this !== self::Zoom;
    }

    public function hasResponsiveImages(): bool
    {
        return $this !== self::Og;
    }

    /**
     * Formati candidati, in ordine di preferenza.
     *
     * @return list<ImageFormat>
     */
    public function formats(): array
    {
        return match ($this) {
            self::Card, self::Hero => [ImageFormat::Avif, ImageFormat::Webp, ImageFormat::Jpeg],
            self::Zoom => [ImageFormat::Webp, ImageFormat::Jpeg],
            self::Og => [ImageFormat::Jpeg],
        };
    }

    /**
     * Se true si generano tutti i formati supportati (un <source> ciascuno); se false
     * solo il primo supportato.
     */
    public function hasPictureSources(): bool
    {
        return $this === self::Card || $this === self::Hero;
    }

    public function conversionName(ImageFormat $format): string
    {
        return $format->isFallback() ? $this->value : $this->value.'-'.$format->value;
    }
}
