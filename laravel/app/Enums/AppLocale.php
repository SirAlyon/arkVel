<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Lingue supportate dall'interfaccia del sito pubblico.
 *
 * Base IT, fallback EN (cfr. config/app.php). Aggiungere una lingua = un nuovo
 * case + i file in lang/<codice>/. L'etichetta è nella lingua stessa (endonimo),
 * così il selettore resta comprensibile qualunque sia la lingua corrente.
 */
enum AppLocale: string implements HasLabel
{
    case Italian = 'it';
    case English = 'en';

    public function getLabel(): string
    {
        return match ($this) {
            self::Italian => 'Italiano',
            self::English => 'English',
        };
    }

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_map(static fn (self $locale): string => $locale->value, self::cases());
    }
}
