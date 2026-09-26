<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Stato del follow-up post-adozione.
 *
 * Traccia se e come l'ente monitora l'adozione dopo la finalizzazione. Alla
 * nascita dell'adozione è `Pending` (da pianificare); l'orchestrazione vera e
 * propria dei follow-up (calendario, promemoria) è demandata a una fase
 * successiva. Enum string + cast Eloquent (mai enum di colonna DB).
 */
enum FollowUpStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case NotNeeded = 'not_needed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Da pianificare',
            self::Scheduled => 'Pianificato',
            self::Completed => 'Completato',
            self::NotNeeded => 'Non necessario',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Scheduled => 'primary',
            self::Completed => 'success',
            self::NotNeeded => 'gray',
        };
    }
}
