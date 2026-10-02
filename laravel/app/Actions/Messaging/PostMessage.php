<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Events\MessageSent;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\User;
use App\Notifications\NewMessageReceived;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;

/**
 * Posta un nuovo messaggio in una conversazione esistente.
 *
 * Single responsibility: unico punto in cui un messaggio viene aggiunto a un
 * thread. La guardia (corpo non vuoto) precede la scrittura. Aggiorna il pivot
 * `last_message_at` del thread e marca "letto" il lato dell'autore (ha appena
 * scritto), poi logga e notifica la CONTROPARTE. Fa da specchio a
 * ConfirmAppointment (guardia → txn → mutazione → log → notify).
 *
 * Il lato dell'autore è determinato confrontandolo con il partecipante adottante
 * del thread: `$author->is($thread->user)` ⇒ lato adottante, altrimenti ente.
 */
final class PostMessage
{
    /**
     * @throws InvalidArgumentException quando il corpo del messaggio è vuoto
     */
    public function handle(MessageThread $thread, User $author, string $body): Message
    {
        $body = trim($body);

        if ($body === '') {
            throw new InvalidArgumentException('Message body cannot be blank.');
        }

        return DB::transaction(function () use ($thread, $author, $body): Message {
            $authorIsAdopter = $author->is($thread->user);
            $now = now();

            $message = $thread->messages()->create([
                'user_id' => $author->getKey(),
                'body' => $body,
            ]);

            // Pivot di ordinamento + lato autore marcato come letto.
            $thread->last_message_at = $now;

            if ($authorIsAdopter) {
                $thread->adopter_last_read_at = $now;
            } else {
                $thread->org_last_read_at = $now;
            }

            $thread->save();

            activity()
                ->performedOn($thread)
                ->causedBy($author)
                ->log('message.posted');

            // Realtime: rinfresca la conversazione aperta dei partecipanti. Payload
            // senza PII; l'evento è ShouldDispatchAfterCommit, quindi il broadcast
            // parte solo dopo il commit (nessun broadcast di un messaggio non persistito).
            broadcast(new MessageSent($thread, $message));

            $this->notifyCounterpart($thread, $message, $authorIsAdopter);

            return $message;
        });
    }

    /**
     * Notifica il lato opposto all'autore: se scrive l'adottante, ricevono i
     * membri verificati dell'ente; se scrive lo staff, riceve l'adottante.
     */
    private function notifyCounterpart(MessageThread $thread, Message $message, bool $authorIsAdopter): void
    {
        if ($authorIsAdopter) {
            Notification::send(
                $thread->organization->verifiedStaff()->get(),
                new NewMessageReceived($thread, $message),
            );

            return;
        }

        $thread->user->notify(new NewMessageReceived($thread, $message));
    }
}
