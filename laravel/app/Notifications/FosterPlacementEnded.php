<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\FosterPlacement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifica all'AFFIDATARIO che l'affido si è concluso: l'animale è rientrato
 * all'ente (o è comunque uscito dall'affido).
 *
 * È emessa da App\Actions\Fostering\EndFosterPlacement verso l'affidatario
 * (user dell'affido). Chiude il ciclo di vita dell'affido lato affidatario.
 * Canali: mail (testo tradotto) + database (alimenta la UI notifiche
 * dell'affidatario).
 *
 * Robustezza: accodata (ShouldQueue) e dispatchata SOLO DOPO il commit
 * ($afterCommit), così l'invio mail sincrono esce dalla transazione di
 * EndFosterPlacement: un intoppo del mailer non fa più rollback della chiusura.
 */
final class FosterPlacementEnded extends Notification implements ShouldQueue
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
            ->subject(__('fostering.placement_ended.subject', ['animal' => $animal]))
            ->greeting(__('fostering.placement_ended.greeting', ['name' => $name]))
            ->line(__('fostering.placement_ended.line', ['animal' => $animal]))
            ->action(
                __('fostering.placement_ended.action'),
                url('/account'),
            )
            ->salutation(__('fostering.placement_ended.salutation'));
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
            'message' => __('fostering.placement_ended.database', ['animal' => $animal]),
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
