<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Stato operativo di una connessione a un calendario esterno.
 *
 * Enum applicativo salvato come string + cast Eloquent (mai enum DB). Non è una
 * macchina a stati di dominio (nessun grafo di transizioni): è un flag operativo
 * che la pipeline di push aggiorna — Active finché il push riesce, Error quando un
 * provider fallisce (con last_error), Disconnected su richiesta dell'utente.
 *
 *  - Active       → connessione sana, gli appuntamenti vengono sincronizzati.
 *  - Error        → l'ultimo push/pull è fallito (dettaglio in last_error); da
 *    riconnettere/ricontrollare. La connessione non viene cancellata (storico).
 *  - Disconnected → sospesa dall'utente: non partecipa più al push.
 */
enum CalendarConnectionStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Error = 'error';
    case Disconnected = 'disconnected';

    public function getLabel(): string
    {
        return __('enums.calendar_connection_status.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Error => 'danger',
            self::Disconnected => 'gray',
        };
    }
}
