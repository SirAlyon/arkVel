<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Adoption;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * Notifica all'ADOTTANTE che l'adozione è stata finalizzata (candidatura
 * Completed).
 *
 * È emessa da App\Actions\Adoptions\FinalizeAdoption verso l'adottante. La mail
 * conferma l'adozione e ALLEGA il contratto PDF, letto dal disco PRIVATO
 * 'local' (storage/app/private/contracts/...): l'allegato viaggia in email, il
 * file non è mai esposto pubblicamente. Canali: mail + database (alimenta la UI
 * notifiche dell'adottante).
 *
 * Robustezza (slice 11): accodata (ShouldQueue) e dispatchata SOLO DOPO il commit
 * ($afterCommit), così l'invio mail sincrono esce dalla transazione di
 * FinalizeAdoption: un intoppo del mailer non fa più rollback di un'adozione
 * valida. L'Adoption viaggia serializzata (SerializesModels, via Queueable) e
 * viene ricaricata al momento dell'invio: il contratto è letto dal disco privato
 * ALLA SEND, quando il file esiste già (scritto in transazione prima del commit).
 */
final class AdoptionCompleted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Adoption $adoption,
    ) {
        // Dispatch solo dopo il commit della transazione circostante (flag
        // $afterCommit del trait Queueable, nessuna ridefinizione di proprietà).
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
        $animal = $this->adoption->animal->name;
        $name = $this->adoption->user->name;

        $message = (new MailMessage)
            ->subject(__('adoptions.completed.subject', ['animal' => $animal]))
            ->greeting(__('adoptions.completed.greeting', ['name' => $name]))
            ->line(__('adoptions.completed.line', ['animal' => $animal]))
            ->line(__('adoptions.completed.contract_line'))
            ->action(__('adoptions.completed.action'), url('/account'))
            ->salutation(__('adoptions.completed.salutation'));

        // Allega il contratto PDF dal disco privato 'local' (se presente).
        $path = $this->adoption->contract_path;

        if ($path !== null && Storage::disk('local')->exists($path)) {
            $message->attachData(
                Storage::disk('local')->get($path),
                __('adoptions.completed.contract_filename', ['id' => $this->adoption->getKey()]),
                ['mime' => 'application/pdf'],
            );
        }

        return $message;
    }

    /**
     * Payload persistito sul canale database: consumato dalla UI notifiche
     * dell'adottante.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $animal = $this->adoption->animal->name;

        return [
            'adoption_id' => $this->adoption->getKey(),
            'animal_id' => $this->adoption->animal_id,
            'animal' => $animal,
            'message' => __('adoptions.completed.database', ['animal' => $animal]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
