<?php

declare(strict_types=1);

namespace App\Support\Seo;

use App\Enums\AppLocale;

/**
 * Lingua dell'app → `og:locale` (formato `language_TERRITORY` di Open Graph).
 * L'inglese è quello britannico (en_GB): il sito si rivolge all'Europa.
 */
final class OpenGraphLocale
{
    public static function for(string $locale): string
    {
        return match (AppLocale::tryFrom($locale)) {
            AppLocale::English => 'en_GB',
            AppLocale::Italian, null => 'it_IT',
        };
    }
}
