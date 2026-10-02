<?php

declare(strict_types=1);

use App\Models\MessageThread;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Spatie\Permission\PermissionRegistrar;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Autorizzazione dei canali privati del realtime (Reverb). Ogni callback è la
| guardia di accesso al canale: ritorna true solo per chi può sottoscriverlo.
| È il CONFINE di sicurezza del websocket — speculare alle Policy del dominio.
|
*/

/**
 * Canale privato per-utente della campanella notifiche Filament.
 *
 * È il canale di default di Laravel (`App.Models.User.{id}`) su cui Filament
 * trasmette l'evento di notifica database: la campanella si aggiorna live senza
 * reload. Autorizzato SOLO al diretto interessato: nessuno può ascoltare le
 * notifiche altrui.
 */
Broadcast::channel('App.Models.User.{id}', function (User $user, int|string $id): bool {
    return (int) $user->getKey() === (int) $id;
});

/**
 * Canale privato per-conversazione: trasporta App\Events\MessageSent (solo id,
 * nessuna PII) ai partecipanti di un thread così che la vista aperta si rinfreschi.
 *
 * L'autorizzazione delega alla MessageThreadPolicy::view, UNICA fonte di verità:
 * vede il canale l'adottante partecipante OPPURE lo staff VERIFICATO dell'ente (con
 * permesso di vista). Un adottante diverso, un non-membro e lo staff di un altro ente
 * (cross-tenant) sono negati. Il binding `{messageThread}` risolve il thread per id;
 * un id inesistente → 404 nel binding, quindi nessuna sottoscrizione.
 *
 * TEAM SPATIE: la rotta /broadcasting/auth vive FUORI dai pannelli Filament, quindi il
 * middleware che imposta il team dal tenant NON gira → il permesso team-scoped
 * MessageView risulterebbe assente e lo staff verificato verrebbe NEGATO. Allineiamo il
 * team all'ente del thread PRIMA del controllo (l'ownership dell'adottante è comunque
 * team-independent). Stesso pattern di CalendarFeedController::appointment.
 */
Broadcast::channel('message-thread.{messageThread}', function (User $user, MessageThread $messageThread): bool {
    app(PermissionRegistrar::class)->setPermissionsTeamId($messageThread->organization_id);

    return $user->can('view', $messageThread);
});
