<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Tipologia di voce in cartella sanitaria (medical_records).
 *
 * Enum applicativo salvato come string + cast Eloquent (mai enum DB). I value
 * sono l'unica fonte di verità: niente magic string nel dominio o in Filament.
 */
enum MedicalRecordType: string implements HasColor, HasLabel
{
    case Vaccination = 'vaccination';
    case Treatment = 'treatment';
    case Surgery = 'surgery';
    case Checkup = 'checkup';
    case Deworming = 'deworming';

    public function getLabel(): string
    {
        return match ($this) {
            self::Vaccination => 'Vaccinazione',
            self::Treatment => 'Trattamento',
            self::Surgery => 'Intervento chirurgico',
            self::Checkup => 'Visita di controllo',
            self::Deworming => 'Sverminazione',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Vaccination => 'success',
            self::Treatment => 'info',
            self::Surgery => 'danger',
            self::Checkup => 'gray',
            self::Deworming => 'warning',
        };
    }
}
