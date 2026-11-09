<?php

declare(strict_types=1);

namespace App\Actions\Messaging;

use App\Data\Messaging\AnimalContactResult;
use App\Models\Animal;
use App\Models\MessageThread;
use App\Models\User;
use InvalidArgumentException;

/**
 * Un adottante scrive all'ente che si prende cura di un animale (CTA "Contatta l'ente"
 * della scheda pubblica).
 *
 * Regola di RIUSO: una conversazione per terna (adottante, ente, animale) non si
 * duplica. Se esiste già, vi si posta il messaggio ({@see PostMessage}); altrimenti se
 * ne avvia una nuova ({@see StartMessageThread}). Con più thread per la stessa terna
 * (dati storici) si usa il più recente per `last_message_at`. La ricerca è
 * ownership-scoped per `user_id`: nessuna fuga cross-utente.
 *
 * Persistenza, activity log, broadcast e notifica alla controparte restano nelle due
 * Action composte: qui si decide solo quale delle due usare.
 */
final class ContactOrganizationAboutAnimal
{
    public function __construct(
        private readonly StartMessageThread $startMessageThread,
        private readonly PostMessage $postMessage,
    ) {}

    /**
     * @throws InvalidArgumentException quando il corpo del messaggio è vuoto
     */
    public function handle(User $adopter, Animal $animal, string $body): AnimalContactResult
    {
        $existing = MessageThread::query()
            ->where('user_id', $adopter->getKey())
            ->where('organization_id', $animal->organization_id)
            ->where('animal_id', $animal->getKey())
            ->latest('last_message_at')
            ->first();

        if ($existing !== null) {
            $this->postMessage->handle($existing, $adopter, $body);

            return new AnimalContactResult($existing, reusedExistingThread: true);
        }

        $thread = $this->startMessageThread->handle(
            author: $adopter,
            organization: $animal->organization,
            adopter: $adopter,
            body: $body,
            animal: $animal,
        );

        return new AnimalContactResult($thread, reusedExistingThread: false);
    }
}
