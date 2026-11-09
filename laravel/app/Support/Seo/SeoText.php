<?php

declare(strict_types=1);

namespace App\Support\Seo;

use Illuminate\Support\Str;

/**
 * Testo per meta description e dati strutturati: senza markup, su una riga, troncato
 * a parola intera. I testi vengono da descrizioni libere degli enti, scritte con il
 * RichEditor di Filament, quindi in HTML:
 *  - i tag di blocco (paragrafi, titoli, voci di elenco, …) e `<br>` diventano uno spazio
 *    PRIMA di togliere i tag, altrimenti `<p>Uno</p><p>Due</p>` diventerebbe «UnoDue»;
 *  - le entità (`&amp;`, `&nbsp;`, `&#39;`, …) sono decodificate: il layout riapplica
 *    l'escape una volta sola stampando il meta;
 *  - ogni sequenza di spazi, a capo e spazi non separabili (U+00A0) diventa uno spazio.
 */
final class SeoText
{
    public const int DESCRIPTION_LENGTH = 160;

    /**
     * Tag che separano blocchi di testo (o righe): al loro posto va uno spazio.
     */
    private const string BLOCK_TAGS = 'address|article|aside|blockquote|br|dd|div|dl|dt|figcaption|figure|footer|h[1-6]|header|hr|li|ol|p|pre|section|table|tbody|td|tfoot|th|thead|tr|ul';

    /**
     * Estratto ripulito, o null se il testo è vuoto (il chiamante ripiega su un default).
     */
    public static function excerpt(?string $text, int $limit = self::DESCRIPTION_LENGTH): ?string
    {
        $plain = self::plain((string) $text);

        if ($plain === '') {
            return null;
        }

        return Str::limit($plain, $limit, '…', preserveWords: true);
    }

    /**
     * Testo semplice su una riga, da HTML.
     */
    public static function plain(string $html): string
    {
        $spaced = (string) preg_replace('#</?(?:'.self::BLOCK_TAGS.')\b[^>]*>#iu', ' ', $html);
        $decoded = html_entity_decode(strip_tags($spaced), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/[\s\x{00A0}]+/u', ' ', $decoded));
    }
}
