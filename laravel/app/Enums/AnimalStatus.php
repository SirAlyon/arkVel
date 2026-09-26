<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum AnimalStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Available = 'available';
    case Reserved = 'reserved';
    case AdoptionPending = 'adoption_pending';
    case Adopted = 'adopted';
    case Fostered = 'fostered';
    case MedicalHold = 'medical_hold';
    case NotAvailable = 'not_available';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Bozza',
            self::Available => 'Disponibile',
            self::Reserved => 'Prenotato',
            self::AdoptionPending => 'Adozione in corso',
            self::Adopted => 'Adottato',
            self::Fostered => 'In affido',
            self::MedicalHold => 'Fermo sanitario',
            self::NotAvailable => 'Non disponibile',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Available => 'success',
            self::Reserved, self::AdoptionPending => 'warning',
            self::Adopted => 'info',
            self::Fostered => 'primary',
            self::MedicalHold => 'danger',
            self::NotAvailable => 'gray',
        };
    }
}
