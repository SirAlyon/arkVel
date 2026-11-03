<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Tipologia di abitazione dichiarata nel questionario dell'adottante.
 *
 * Enum applicativo salvato come string + cast Eloquent (mai enum DB). I value
 * sono l'unica fonte di verità: niente magic string nel dominio o in Filament.
 */
enum HousingType: string implements HasLabel
{
    case Apartment = 'apartment';
    case House = 'house';
    case Farm = 'farm';
    case Other = 'other';

    public function getLabel(): string
    {
        return __('enums.housing_type.'.$this->value);
    }
}
