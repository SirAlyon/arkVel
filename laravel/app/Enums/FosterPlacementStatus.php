<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Stato del ciclo di vita di un affido fisico (foster_placements).
 *
 * Lifecycle SEMPLICE (Active/Ended), NON una macchina a stati: la macchina a
 * stati vive su FosterApplication (App\States\Foster). Il passaggio
 * Active → Ended è pilotato dalle Action della slice 3, non mutando la colonna
 * a mano nell'UI.
 *
 * Enum applicativo salvato come string + cast Eloquent (mai enum DB).
 */
enum FosterPlacementStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Ended = 'ended';

    public function getLabel(): string
    {
        return __('enums.foster_placement_status.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Ended => 'gray',
        };
    }
}
