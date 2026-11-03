<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Events\MessageSent;
use App\Models\AdoptionApplication;
use App\Models\Animal;
use App\Models\Message;
use App\Models\MessageThread;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\NewMessageReceived;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;

/**
 * Avvia una conversazione ente↔adottante e vi posta il primo messaggio.
 *
 * Single responsibility: unico punto in cui un thread nasce. La guardia (corpo
 * non vuoto) è verificata PRIMA di scrivere, così un rifiuto non lascia righe
 * orfane. La persistenza, la tracciatura (activity log) e la notifica alla
 * controparte sono orchestrate qui, non nel model. Fa da specchio a
 * SubmitFosterApplication (guardia → txn → create → log → notify).
 *
 * Partecipanti: l'ENTE (organization) e l'ADOTTANTE (adopter). L'AUTORE del primo
 * messaggio può essere uno dei due lati; marca il proprio lato come "letto"
 * (ha appena scritto, quindi ha "letto" il proprio messaggio) e la notifica va
 * all'ALTRO lato.
 */
final class StartMessageThread
{
    /**
     * @throws InvalidArgumentException quando il corpo del messaggio è vuoto
     */
    public function handle(
        User $author,
        Organization $organization,
        User $adopter,
        string $body,
        ?string $subject = null,
        ?Animal $animal = null,
        ?AdoptionApplication $application = null,
    ): MessageThread {
        $body = trim($body);

        if ($body === '') {
            throw new InvalidArgumentException('Message body cannot be blank.');
        }

        return DB::transaction(function () use (
            $author,
            $organization,
            $adopter,
            $body,
            $subject,
            $animal,
            $application,
        ): MessageThread {
            // L'autore è l'adottante se coincide con il partecipante adottante;
            // in tal caso marchiamo letto il lato adottante, altrimenti il lato ente.
            $authorIsAdopter = $author->is($adopter);
            $now = now();

            $thread = MessageThread::query()->create([
                'organization_id' => $organization->getKey(),
                'user_id' => $adopter->getKey(),
                'animal_id' => $animal?->getKey(),
                'adoption_application_id' => $application?->getKey(),
                'subject' => $subject,
                'last_message_at' => $now,
                'adopter_last_read_at' => $authorIsAdopter ? $now : null,
                'org_last_read_at' => $authorIsAdopter ? null : $now,
            ]);

            $message = $thread->messages()->create([
                'user_id' => $author->getKey(),
                'body' => $body,
            ]);

            activity()
                ->performedOn($thread)
                ->causedBy($author)
                ->log('message_thread.started');

            // Realtime: rinfresca la conversazione aperta dei partecipanti. Payload
            // senza PII; l'evento è ShouldDispatchAfterCommit, quindi il broadcast
            // parte solo dopo il commit (nessun broadcast di un thread non persistito).
            broadcast(new MessageSent($thread, $message));

            $this->notifyCounterpart($thread, $message, $authorIsAdopter);

            return $thread;
        });
    }

    /**
     * Notifica il lato opposto all'autore: se scrive l'adottante, ricevono i
     * membri verificati dell'ente; se scrive lo staff, riceve l'adottante. La
     * notifica è accodata after-commit: parte solo dopo il commit. Recipient
     * vuoto → Notification::send è un no-op sicuro.
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
