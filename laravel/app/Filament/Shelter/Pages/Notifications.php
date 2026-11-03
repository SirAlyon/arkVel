<?php

declare(strict_types=1);

namespace App\Filament\Shelter\Pages;

use App\Filament\Notifications\Support\InteractsWithNotificationCenter;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;

/**
 * Centro notifiche del pannello ente (/manage): storico completo delle notifiche
 * in-app dell'utente, oltre alla campanella (che mostra solo le recenti).
 *
 * Pagina SOTTILE: tutto il comportamento (tabella, badge, azioni di lettura) vive
 * in {@see InteractsWithNotificationCenter}, condiviso con il pannello adottante.
 *
 * Lo scoping è per OWNERSHIP, non per tenant: le notifiche appartengono alla
 * persona (membro dello staff), non all'ente. La query interroga la relazione
 * `notifications()` dell'utente autenticato: ogni utente vede solo le proprie,
 * indipendentemente dal tenant corrente.
 */
class Notifications extends Page implements HasTable
{
    use InteractsWithNotificationCenter, InteractsWithTable {
        // Il centro notifiche definisce la propria table(); risolve la collisione
        // con la table() di default di InteractsWithTable.
        InteractsWithNotificationCenter::table insteadof InteractsWithTable;
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBell;

    protected string $view = 'filament.shelter.pages.notifications';

    protected static ?int $navigationSort = 50;
}
