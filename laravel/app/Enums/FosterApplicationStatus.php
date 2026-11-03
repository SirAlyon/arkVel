<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Stati della richiesta di affido (temporary fostering).
 *
 * Analogo alla candidatura di adozione (ApplicationStatus): il grafo delle
 * transizioni consentite è implementato nella macchina a stati
 * (spatie/laravel-model-states) in App\States\Foster — vedi
 * docs/PIANO-DI-SVILUPPO.md §Fase 3.
 */
enum FosterApplicationStatus: string implements HasColor, HasLabel
{
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Waitlisted = 'waitlisted';
    case Active = 'active';
    case Completed = 'completed';
    case Withdrawn = 'withdrawn';

    public function getLabel(): string
    {
        return __('enums.foster_application_status.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Submitted => 'gray',
            self::UnderReview => 'warning',
            self::Approved, self::Active => 'primary',
            self::Completed => 'success',
            self::Waitlisted => 'info',
            self::Rejected, self::Withdrawn => 'danger',
        };
    }
}
