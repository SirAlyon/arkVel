<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Tipologia di una donazione: una tantum oppure ricorrente.
 *
 * `OneOff`      → pagamento singolo (Stripe Checkout one-time, P4-6).
 * `Recurring`   → donazione ricorrente (Stripe subscription, P4-7).
 *
 * Enum applicativo salvato come string + cast Eloquent (mai enum di colonna DB). I value
 * sono l'unica fonte di verità: niente magic string nel dominio o in Filament.
 */
enum DonationType: string implements HasColor, HasLabel
{
    case OneOff = 'one_off';
    case Recurring = 'recurring';

    public function getLabel(): string
    {
        return match ($this) {
            self::OneOff => 'Una tantum',
            self::Recurring => 'Ricorrente',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::OneOff => 'gray',
            self::Recurring => 'info',
        };
    }
}
