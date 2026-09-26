<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Livello di energia dell'animale, usato per il matching con l'adottante e la
 * scheda anagrafica. Nullable in DB: assente = "sconosciuto".
 */
enum EnergyLevel: string implements HasLabel
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function getLabel(): string
    {
        return match ($this) {
            self::Low => 'Basso',
            self::Medium => 'Medio',
            self::High => 'Alto',
        };
    }
}
