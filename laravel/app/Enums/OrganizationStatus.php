<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OrganizationStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Suspended = 'suspended';

    public function getLabel(): string
    {
        return __('enums.organization_status.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Verified => 'success',
            self::Suspended => 'danger',
        };
    }
}
