<?php

declare(strict_types=1);

namespace App\Support\Seo;

/**
 * Codifica JSON-LD sicura da stampare senza escape dentro un
 * `<script type="application/ld+json">`.
 *
 * - JSON_HEX_TAG trasforma `<` e `>` in `<`/`>`: un testo come
 *   `</script><script>…` scritto da un ente non può chiudere il tag;
 * - JSON_HEX_AMP evita ambiguità con le entità HTML;
 * - JSON_UNESCAPED_SLASHES / JSON_UNESCAPED_UNICODE tengono leggibili URL e accenti
 *   (U+2028/U+2029 restano comunque escapati da PHP);
 * - JSON_INVALID_UTF8_SUBSTITUTE: un byte non valido nel contenuto non fa fallire la
 *   pagina, viene sostituito.
 */
final class JsonLd
{
    public const int FLAGS = JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_UNESCAPED_SLASHES
        | JSON_UNESCAPED_UNICODE
        | JSON_INVALID_UTF8_SUBSTITUTE
        | JSON_THROW_ON_ERROR;

    /**
     * @param  array<string, mixed>  $document
     */
    public static function encode(array $document): string
    {
        return json_encode($document, self::FLAGS);
    }
}
