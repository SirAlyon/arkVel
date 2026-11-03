<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Stati della candidatura di adozione.
 *
 * Il grafo delle transizioni consentite è implementato nella macchina a stati
 * (spatie/laravel-model-states) in App\States\Application — vedi
 * docs/PIANO-DI-SVILUPPO.md §5.
 */
enum ApplicationStatus: string implements HasColor, HasLabel
{
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Interview = 'interview';
    case Approved = 'approved';
    case AdoptionPending = 'adoption_pending';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
    case Waitlisted = 'waitlisted';

    public function getLabel(): string
    {
        return __('enums.application_status.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Submitted => 'gray',
            self::UnderReview, self::Interview => 'warning',
            self::Approved, self::AdoptionPending => 'primary',
            self::Completed => 'success',
            self::Rejected, self::Withdrawn => 'danger',
            self::Waitlisted => 'info',
        };
    }
}
