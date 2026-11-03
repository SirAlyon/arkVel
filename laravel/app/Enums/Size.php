<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Size: string implements HasLabel
{
    case Small = 'small';
    case Medium = 'medium';
    case Large = 'large';

    public function getLabel(): string
    {
        return __('enums.size.'.$this->value);
    }
}
