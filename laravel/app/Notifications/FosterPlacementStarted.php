<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\FosterPlacement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifica all'AFFIDATARIO che l'affido è iniziato: l'animale è stato collocato
 * temporaneamente presso di lui.
 *
 * È emessa da App\Actions\Fostering\StartFosterPlacement verso l'affidatario
 * (user dell'affido). Chiude il lato "consegna" del flusso di affido, come
 * AdoptionCompleted fa per l'adozione. Canali: mail (testo tradotto) + database
 * (alimenta la UI notifiche dell'affidatario).
 *
 * Robustezza: accodata (ShouldQueue) e dispatchata SOLO DOPO il commit
 * ($afterCommit), così l'invio mail sincrono esce dalla transazione di
 * StartFosterPlacement: un intoppo del mailer non fa più rollback di un affido
 * valido.
 */
final class FosterPlacementStarted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly FosterPlacement $placement,
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
        $animal = $this->placement->animal->name;
        $name = $this->placement->user->name;

        return (new MailMessage)
            ->subject(__('fostering.placement_started.subject', ['animal' => $animal]))
            ->greeting(__('fostering.placement_started.greeting', ['name' => $name]))
            ->line(__('fostering.placement_started.line', ['animal' => $animal]))
            ->action(
                __('fostering.placement_started.action'),
                url('/account'),
            )
            ->salutation(__('fostering.placement_started.salutation'));
    }

    /**
     * Payload persistito sul canale database: consumato dalla UI notifiche
     * dell'affidatario.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $animal = $this->placement->animal->name;

        return [
            'foster_placement_id' => $this->placement->getKey(),
            'animal_id' => $this->placement->animal_id,
            'animal' => $animal,
            'message' => __('fostering.placement_started.database', ['animal' => $animal]),
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
