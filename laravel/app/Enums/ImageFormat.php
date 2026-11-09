<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Formati delle conversioni immagine (medialibrary) del sito pubblico.
 *
 * L'ordine dei case è quello di preferenza nel <picture> di x-ui.picture: il browser usa
 * il primo <source> che sa decodificare (AVIF, poi WebP) e altrimenti l'<img> in JPEG,
 * che esiste sempre. I formati moderni si generano solo se il driver immagine li
 * supporta (App\Support\Media\ImageFormatSupport). Si generano in ordine inverso, JPEG
 * per primo (App\Support\Media\ImageConversions): un AVIF che fallisce non blocca il resto.
 */
enum ImageFormat: string
{
    case Avif = 'avif';
    case Webp = 'webp';
    case Jpeg = 'jpg';

    public function mimeType(): string
    {
        return match ($this) {
            self::Avif => 'image/avif',
            self::Webp => 'image/webp',
            self::Jpeg => 'image/jpeg',
        };
    }

    /**
     * Qualità di codifica (0-100). A parità di resa percepita AVIF e WebP reggono
     * valori più bassi del JPEG: sono questi a far risparmiare byte.
     */
    public function quality(): int
    {
        return match ($this) {
            self::Avif => 55,
            self::Webp => 78,
            self::Jpeg => 82,
        };
    }

    /**
     * JPEG è il fallback universale: sempre generato, è l'<img> del <picture>.
     */
    public function isFallback(): bool
    {
        return $this === self::Jpeg;
    }
}
