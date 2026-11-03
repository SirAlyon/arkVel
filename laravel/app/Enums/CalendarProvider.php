<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\Calendar\CalendarProviderRegistry;
use App\Support\Calendar\Providers\ExternalCalendarProvider;
use Filament\Support\Contracts\HasLabel;

/**
 * Provider di calendario esterno verso cui ArkVel sincronizza gli appuntamenti.
 *
 * Enum applicativo salvato come string + cast Eloquent (mai enum DB). I value sono
 * l'unica fonte di verità: niente magic string nel dominio o in Filament, e sono
 * la chiave con cui {@see CalendarProviderRegistry} risolve
 * l'implementazione {@see ExternalCalendarProvider}
 * corrispondente.
 *
 *  - Google  → Google Calendar (OAuth) — implementazione in Cal-5.
 *  - CalDav  → collezione CalDAV (Apple/Fastmail/Nextcloud) — Cal-6.
 *  - Log     → provider fittizio/in-memory che logga soltanto: rende l'intera
 *    pipeline dimostrabile e testabile end-to-end SENZA credenziali reali (demo/test).
 */
enum CalendarProvider: string implements HasLabel
{
    case Google = 'google';
    case CalDav = 'caldav';
    case Log = 'log';

    public function getLabel(): string
    {
        return __('enums.calendar_provider.'.$this->value);
    }
}
