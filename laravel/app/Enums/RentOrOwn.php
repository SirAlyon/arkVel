<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Titolo di godimento dell'abitazione dell'adottante (proprietà o affitto).
 *
 * Enum applicativo salvato come string + cast Eloquent (mai enum DB). I value
 * sono l'unica fonte di verità: niente magic string nel dominio o in Filament.
 */
enum RentOrOwn: string implements HasLabel
{
    case Own = 'own';
    case Rent = 'rent';

    public function getLabel(): string
    {
        return __('enums.rent_or_own.'.$this->value);
    }
}
