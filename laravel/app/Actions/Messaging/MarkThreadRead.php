<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Models\MessageThread;
use App\Models\User;

/**
 * Marca una conversazione come letta per il lato del lettore.
 *
 * Single responsibility: unico punto in cui un marcatore di lettura avanza. Il
 * lato è determinato dal lettore: `$reader->is($thread->user)` ⇒ lato adottante,
 * altrimenti lato ente. Nessuna notifica (la lettura è un evento silenzioso).
 *
 * L'autorizzazione (chi può leggere il thread) resta alla MessageThreadPolicy nel
 * chiamante: qui si assume che il lettore sia un partecipante legittimo.
 */
final class MarkThreadRead
{
    public function handle(MessageThread $thread, User $reader): void
    {
        $now = now();

        if ($reader->is($thread->user)) {
            $thread->adopter_last_read_at = $now;
        } else {
            $thread->org_last_read_at = $now;
        }

        $thread->save();
    }
}
