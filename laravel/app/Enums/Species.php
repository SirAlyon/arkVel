<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Species: string implements HasLabel
{
    case Dog = 'dog';
    case Cat = 'cat';
    case Other = 'other';

    public function getLabel(): string
    {
        return __('enums.species.'.$this->value);
    }
}
