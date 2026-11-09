<?php

declare(strict_types=1);

namespace App\Support\Media;

use App\Enums\ImageFormat;
use Illuminate\Container\Attributes\Bind;

/**
 * Quali formati il driver immagine della medialibrary sa leggere e scrivere.
 *
 * Le conversioni WebP/AVIF si registrano solo per i formati supportati: con un GD
 * compilato senza libwebp/libavif una conversione AVIF fallirebbe nel job in coda,
 * mentre così l'ambiente degrada da solo a WebP o a JPEG. I test possono legare
 * un'implementazione finta per provare ogni combinazione.
 */
#[Bind(DriverImageFormatSupport::class)]
interface ImageFormatSupport
{
    public function supports(ImageFormat $format): bool;
}
