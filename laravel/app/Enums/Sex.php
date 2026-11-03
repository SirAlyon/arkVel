<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Sex: string implements HasLabel
{
    case Male = 'male';
    case Female = 'female';

    public function getLabel(): string
    {
        return __('enums.sex.'.$this->value);
    }
}
