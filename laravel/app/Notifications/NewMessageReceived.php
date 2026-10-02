<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Message;
use App\Models\MessageThread;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Notifica alla CONTROPARTE di una conversazione che è arrivato un nuovo
 * messaggio.
 *
 * È emessa dalle Action App\Actions\Messaging\StartMessageThread e PostMessage
 * verso il lato opposto al mittente: se scrive l'adottante, ricevono i membri
 * VERIFICATI dell'ente; se scrive lo staff, riceve l'adottante. La STESSA classe
 * serve entrambi i lati: il link è derivato dal destinatario (notifiable), così
 * l'adottante atterra sul proprio account e lo staff sulla dashboard dell'ente.
 *
 * Canali: mail (testo tradotto) + database (UI notifiche). Accodata (ShouldQueue)
 * e dispatchata SOLO DOPO il commit ($afterCommit) della transazione dell'Action:
 * un intoppo del mailer non fa rollback del messaggio.
 */
final class NewMessageReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly MessageThread $thread,
        public readonly Message $message,
    ) {
        // Dispatch solo dopo il commit della transazione dell'Action.
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $sender = $this->message->user->name;

        return (new MailMessage)
            ->subject(__('messaging.new_message.subject', ['sender' => $sender]))
            ->greeting(__('messaging.new_message.greeting'))
            ->line(__('messaging.new_message.line', ['sender' => $sender]))
            ->line(Str::limit($this->message->body, 240))
            ->action(
                __('messaging.new_message.action'),
                $this->urlFor($notifiable),
            )
            ->salutation(__('messaging.new_message.salutation'));
    }

    /**
     * Payload persistito sul canale database: consumato dalla UI notifiche.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $sender = $this->message->user->name;

        return [
            'message_thread_id' => $this->thread->getKey(),
            'message_id' => $this->message->getKey(),
            'organization_id' => $this->thread->organization_id,
            'sender' => $sender,
            'excerpt' => Str::limit($this->message->body, 140),
            'message' => __('messaging.new_message.database', ['sender' => $sender]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }

    /**
     * Link best-effort, derivato dal destinatario: l'adottante partecipante
     * atterra sul proprio account; chiunque altro (staff dell'ente) sulla
     * dashboard dell'ente. Se la rotta shelter non fosse registrata (contesti di
     * test/futuri refactor) ricadiamo sulla base del pannello, senza mai
     * sollevare eccezioni.
     */
    private function urlFor(object $notifiable): string
    {
        if ($notifiable instanceof User && $notifiable->is($this->thread->user)) {
            return url('/account');
        }

        $organization = $this->thread->organization;

        try {
            return route('filament.shelter.pages.dashboard', ['tenant' => $organization->slug]);
        } catch (\Throwable) {
            return url('/manage');
        }
    }
}
