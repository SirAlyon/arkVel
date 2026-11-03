<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Message;
use App\Models\MessageThread;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento di broadcast "è arrivato un messaggio in questa conversazione".
 *
 * Emesso dalle Action App\Actions\Messaging\{StartMessageThread,PostMessage} DOPO il
 * commit, verso il canale privato per-thread `message-thread.{id}`, autorizzato dalla
 * MessageThreadPolicy::view (vedi routes/channels.php). Serve solo a far rinfrescare la
 * conversazione aperta nei pannelli: il client, ricevuto l'evento, rifetcha lo storico
 * tramite il canale Livewire già autorizzato.
 *
 * MINIMIZZAZIONE / NO PII: il payload trasmesso contiene SOLO identificatori e il lato
 * mittente ("adopter"/"org") — mai il nome del mittente né il corpo del messaggio. I
 * contenuti restano dietro l'autorizzazione Livewire. Il canale è comunque privato +
 * policy-gated, ma preferiamo non far transitare PII sul websocket.
 *
 * ShouldDispatchAfterCommit: l'evento è dispatchato dalle Action DENTRO la transazione
 * ma propagato SOLO dopo il commit — un rollback non lascia trapelare un broadcast di un
 * messaggio mai persistito.
 */
final class MessageSent implements ShouldBroadcast, ShouldDispatchAfterCommit
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public readonly int $threadId;

    public readonly int $messageId;

    /**
     * Lato mittente: "adopter" se il messaggio è dell'adottante partecipante,
     * altrimenti "org". Nessun dato personale.
     */
    public readonly string $senderSide;

    public function __construct(MessageThread $thread, Message $message)
    {
        $this->threadId = (int) $thread->getKey();
        $this->messageId = (int) $message->getKey();
        $this->senderSide = (int) $message->user_id === (int) $thread->user_id
            ? 'adopter'
            : 'org';
    }

    /**
     * Canale privato della conversazione. L'autorizzazione (chi può sottoscrivere)
     * vive in routes/channels.php, delegata alla MessageThreadPolicy::view.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('message-thread.'.$this->threadId),
        ];
    }

    /**
     * Nome dell'evento lato client (con il punto iniziale si ascolta con
     * `.listen('.message.sent', ...)`), disaccoppiato dal FQCN della classe.
     */
    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * Payload esplicito: SOLO identificatori + lato mittente. Niente PII (nome,
     * corpo del messaggio): il contenuto si rifetcha via il canale autorizzato.
     *
     * @return array<string, int|string>
     */
    public function broadcastWith(): array
    {
        return [
            'thread_id' => $this->threadId,
            'message_id' => $this->messageId,
            'sender_side' => $this->senderSide,
        ];
    }
}
