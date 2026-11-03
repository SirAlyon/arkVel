<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Direzione della sincronizzazione di una connessione a calendario esterno.
 *
 * Enum applicativo salvato come string + cast Eloquent (mai enum DB).
 *
 *  - Push → una via, ArkVel → esterno: gli appuntamenti ArkVel compaiono nel
 *    calendario esterno, ma le modifiche fatte là NON tornano indietro.
 *  - Both → due vie (default): oltre al push, ArkVel importa/riconcilia le modifiche
 *    dal calendario esterno (pull, Cal-7).
 *
 * Questa slice (Cal-4) implementa SOLO il push; il ramo pull di Both arriva in Cal-7.
 */
enum CalendarSyncDirection: string implements HasLabel
{
    case Push = 'push';
    case Both = 'both';

    public function getLabel(): string
    {
        return __('enums.calendar_sync_direction.'.$this->value);
    }
}
