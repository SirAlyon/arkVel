<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Tipologia di affido fisico (foster_placements): perché l'animale è stato
 * collocato temporaneamente presso l'affidatario.
 *
 * Enum applicativo salvato come string + cast Eloquent (mai enum DB). I value
 * sono l'unica fonte di verità: niente magic string nel dominio o in Filament.
 */
enum FosterPlacementType: string implements HasColor, HasLabel
{
    case PreAdoption = 'pre_adoption';
    case Medical = 'medical';
    case Emergency = 'emergency';
    case LongTerm = 'long_term';

    public function getLabel(): string
    {
        return match ($this) {
            self::PreAdoption => 'Pre-adozione',
            self::Medical => 'Sanitario',
            self::Emergency => 'Emergenza',
            self::LongTerm => 'Lungo termine',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::PreAdoption => 'primary',
            self::Medical => 'warning',
            self::Emergency => 'danger',
            self::LongTerm => 'info',
        };
    }
}
