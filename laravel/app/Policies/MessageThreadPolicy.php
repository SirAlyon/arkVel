<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\MessageThread;
use App\Models\User;

/**
 * Policy per MessageThread.
 *
 * Rispecchia FosterApplicationPolicy: la conversazione ha DUE facce, quindi la
 * policy combina i due pattern del progetto:
 *  - OWNERSHIP: l'adottante partecipante vede e risponde al proprio thread
 *    (`is()`), senza permessi spatie.
 *  - TEAM-SCOPED: lo staff dell'ente vede/risponde ai thread del proprio ente,
 *    con permesso team-scoped + membership VERIFICATA (doppia difesa contro
 *    fughe cross-tenant).
 *
 * I volontari hanno il permesso `message.view` ma NON `message.manage`: possono
 * leggere le conversazioni dell'ente ma non postare messaggi.
 *
 * Il Super Admin bypassa tutto via Gate::before (AppServiceProvider).
 * Registrazione: auto-discovery Laravel (App\Models\X → App\Policies\XPolicy).
 */
class MessageThreadPolicy
{
    /**
     * Le liste sono scoped dal chiamante (ownership adottante o tenant staff):
     * l'accesso all'elenco è permissivo, l'isolamento è a livello di query/riga.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Lo vede l'adottante partecipante, oppure lo staff dell'ente (con permesso
     * di vista e membership verificata). I volontari rientrano qui.
     */
    public function view(User $user, MessageThread $messageThread): bool
    {
        return $user->is($messageThread->user)
            || ($user->can(PermissionName::MessageView->value)
                && $user->isVerifiedMemberOf($messageThread->organization));
    }

    /**
     * Chiunque può avviare una conversazione: le guardie di dominio (corpo non
     * vuoto, coerenza partecipanti) vivono nell'Action.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Chi può POSTARE un messaggio nel thread: l'adottante partecipante, oppure
     * lo staff dell'ente con permesso di GESTIONE + membership verificata. I
     * volontari (solo `message.view`) sono esclusi: leggono ma non rispondono.
     */
    public function reply(User $user, MessageThread $messageThread): bool
    {
        return $user->is($messageThread->user)
            || ($user->can(PermissionName::MessageManage->value)
                && $user->isVerifiedMemberOf($messageThread->organization));
    }

    /**
     * Le conversazioni non si cancellano (tracciabilità/GDPR gestiti altrove).
     * Il Super Admin resta l'unica eccezione, via Gate::before.
     */
    public function delete(User $user, MessageThread $messageThread): bool
    {
        return false;
    }
}
