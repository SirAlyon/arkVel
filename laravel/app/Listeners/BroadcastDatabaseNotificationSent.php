<?php

declare(strict_types=1);

namespace App\Listeners;

use Filament\Notifications\Events\DatabaseNotificationsSent;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Events\NotificationSent;

/**
 * Rende LIVE la campanella notifiche di Filament per QUALSIASI notifica Laravel
 * salvata su database.
 *
 * Filament aggiorna il badge della campanella quando riceve, sul canale privato
 * dell'utente (`App.Models.User.{id}`), l'evento `database-notifications.sent`
 * (vedi Filament\Notifications\Events\DatabaseNotificationsSent). Quell'evento viene
 * emesso nativamente solo dalle notifiche Filament inviate con
 * `isEventDispatched: true`: le notifiche Laravel standard (come
 * App\Notifications\NewMessageReceived) non lo farebbero scattare.
 *
 * Questo listener colma il divario in modo GENERICO e senza PII: all'arrivo di una
 * notifica sul canale `database` verso un utente, ri-emette DatabaseNotificationsSent
 * verso quell'utente. L'evento non trasporta payload (solo l'identità del canale):
 * la campanella si limita a rifetchare le notifiche tramite il componente Livewire
 * già autorizzato. Così il conteggio si aggiorna senza reload, mantenendo il polling
 * (dove ancora attivo) come fallback.
 *
 * Registrato in AppServiceProvider::boot via Event::listen.
 */
final class BroadcastDatabaseNotificationSent
{
    public function handle(NotificationSent $event): void
    {
        if ($event->channel !== 'database') {
            return;
        }

        $notifiable = $event->notifiable;

        if (! $notifiable instanceof Model && ! $notifiable instanceof Authenticatable) {
            return;
        }

        DatabaseNotificationsSent::dispatch($notifiable);
    }
}
