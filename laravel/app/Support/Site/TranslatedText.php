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

            if (is_string($text) && trim($text) !== '') {
                return new self(trim($text), $candidate);
            }
        }

        return null;
    }

    /** Il testo è in una lingua diversa da quella della pagina. */
    public function differsFrom(string $locale): bool
    {
        return $this->locale !== $locale;
    }
}
