<?php

declare(strict_types=1);

namespace App\Support\Site;

/**
 * Un indirizzo web inserito da un ente (il sito, nei dati pubblici), accettato solo se è
 * un URL valido `http` o `https`. Tutto il resto (`javascript:`, `data:`, `mailto:`, testo
 * libero) vale null: non diventa mai un link della pagina né un `sameAs` di schema.org.
 *
 * Unico punto del controllo per la pagina dell'ente e per App\Support\Seo\SchemaOrg.
 */
final class WebUrl
{
    public static function safe(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        if (filter_var($value, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $scheme = parse_url($value, PHP_URL_SCHEME);

        return is_string($scheme) && in_array(mb_strtolower($scheme), ['http', 'https'], true) ? $value : null;
    }
}
