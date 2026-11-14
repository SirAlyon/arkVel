<?php

declare(strict_types=1);

namespace App\Support\Site;

use Illuminate\Database\Eloquent\Model;

/**
 * Un testo traducibile (spatie/laravel-translatable) da mostrare sul sito pubblico, con la
 * lingua in cui è davvero scritto.
 *
 * Si sceglie la lingua corrente; se lì il testo manca, la lingua di fallback dell'app, poi
 * la prima traduzione non vuota. `locale` serve all'attributo `lang` del markup (WCAG
 * 3.1.2): un testo in inglese su una pagina italiana va annunciato in inglese.
 */
final readonly class TranslatedText
{
    public function __construct(
        public string $text,
        public string $locale,
    ) {}

    /**
     * @param  Model  $model  un modello con il trait HasTranslations
     */
    public static function pick(Model $model, string $attribute, string $locale, ?string $fallbackLocale = null): ?self
    {
        if (! method_exists($model, 'getTranslations')) {
            return null;
        }

        /** @var array<string, mixed> $translations */
        $translations = $model->getTranslations($attribute);
        $fallbackLocale ??= (string) config('app.fallback_locale');

        $candidates = array_unique([$locale, $fallbackLocale, ...array_map('strval', array_keys($translations))]);

        foreach ($candidates as $candidate) {
            $text = $translations[$candidate] ?? null;

            if (! is_string($text)) {
                continue;
            }

            // I campi descrizione sono RichEditor (HTML). Il sito pubblico li mostra come
            // TESTO (escaped, whitespace-pre-line): appiattiamo l'HTML a testo pulito, così
            // una descrizione formattata non espone i tag e una "visivamente vuota" come
            // `<p></p>` diventa stringa vuota (nessun tag mostrato).
            $plain = self::toPlainText($text);

            if ($plain !== '') {
                return new self($plain, $candidate);
            }
        }

        return null;
    }

    /**
     * Appiattisce un valore eventualmente HTML (es. dai RichEditor di Filament) a testo
     * semplice: i tag di blocco e gli a-capo diventano newline, gli altri tag sono rimossi,
     * le entità decodificate e gli spazi normalizzati. Un markup senza testo visibile
     * (`<p></p>`, `<p><br></p>`) diventa stringa vuota.
     */
    private static function toPlainText(string $value): string
    {
        $withBreaks = preg_replace(
            '/<\s*(br|\/p|\/div|\/li|\/h[1-6])\s*\/?\s*>/i',
            "\n",
            $value,
        ) ?? $value;

        $stripped = html_entity_decode(strip_tags($withBreaks), ENT_QUOTES | ENT_HTML5);

        /** @var list<string> $lines */
        $lines = preg_split('/\n/', $stripped) ?: [];
        $lines = array_map('trim', $lines);

        $joined = implode("\n", $lines);

        return trim(preg_replace("/\n{3,}/", "\n\n", $joined) ?? $joined);
    }

    /** Il testo è in una lingua diversa da quella della pagina. */
    public function differsFrom(string $locale): bool
    {
        return $this->locale !== $locale;
    }
}
