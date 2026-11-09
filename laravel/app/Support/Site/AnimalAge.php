<?php

declare(strict_types=1);

namespace App\Support\Site;

use App\Models\Animal;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Età di un animale come testo breve e tradotto per il sito pubblico («8 mesi», «3 anni»).
 *
 * Fonte: l'età stimata in mesi (`estimated_age_months`, quella che compila l'ente), oppure,
 * se manca, la data di nascita. Sotto i due anni l'età si legge in mesi, poi in anni
 * compiuti. Senza nessuno dei due dati (o con una data futura) non c'è età da mostrare.
 */
final class AnimalAge
{
    /** Da qui in poi l'età si esprime in anni. */
    public const int MONTHS_BEFORE_YEARS = 24;

    public static function of(Animal $animal, ?CarbonInterface $today = null): ?string
    {
        return self::label($animal->estimated_age_months, $animal->birthdate, $today);
    }

    public static function label(?int $estimatedMonths, ?CarbonInterface $birthdate, ?CarbonInterface $today = null): ?string
    {
        $months = $estimatedMonths ?? self::monthsSince($birthdate, $today ?? Carbon::today());

        if ($months === null || $months < 0) {
            return null;
        }

        if ($months < self::MONTHS_BEFORE_YEARS) {
            $months = max(1, $months);

            return trans_choice('page_animal.age.months', $months, ['count' => $months]);
        }

        $years = intdiv($months, 12);

        return trans_choice('page_animal.age.years', $years, ['count' => $years]);
    }

    private static function monthsSince(?CarbonInterface $birthdate, CarbonInterface $today): ?int
    {
        if ($birthdate === null || $birthdate->greaterThan($today)) {
            return null;
        }

        return (int) $birthdate->diffInMonths($today);
    }
}
