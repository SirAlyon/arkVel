<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum OrganizationType: string implements HasLabel
{
    case CanileComunale = 'canile_comunale';
    case CanilePrivato = 'canile_privato';
    case Rifugio = 'rifugio';
    case Associazione = 'associazione';
    case Societa = 'societa';

    public function getLabel(): string
    {
        return __('enums.organization_type.'.$this->value);
    }
}
