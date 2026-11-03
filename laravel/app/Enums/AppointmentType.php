<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Tipologia di appuntamento fra un adottante/affidatario e un ente su un animale:
 * visita in struttura, colloquio conoscitivo o controllo domiciliare.
 *
 * Enum applicativo salvato come string + cast Eloquent (mai enum DB). I value sono
 * l'unica fonte di verità: niente magic string nel dominio o in Filament.
 */
enum AppointmentType: string implements HasColor, HasLabel
{
    case Visit = 'visit';
    case Interview = 'interview';
    case HomeCheck = 'home_check';

    public function getLabel(): string
    {
        return __('enums.appointment_type.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Visit => 'success',
            self::Interview => 'info',
            self::HomeCheck => 'warning',
        };
    }
}
