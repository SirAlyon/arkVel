<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\FosterApplicationStatus;
use App\Models\FosterApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifica all'AFFIDATARIO che lo stato della sua richiesta di affido è cambiato.
 *
 * È emessa da App\Actions\Fostering\AdvanceFosterApplication (transizione staff)
 * verso il proprietario della richiesta. Trasporta la coppia from/to come enum
 * per tenere UI/messaggi allineati all'unica fonte di verità delle label. Fa da
 * specchio a ApplicationStatusChanged (adozione). Canali: mail (testo tradotto)
 * + database (alimenta la UI notifiche affidatario).
 *
 * Robustezza: la notifica è accodata (ShouldQueue) e dispatchata SOLO DOPO il
 * commit della transazione circostante ($afterCommit). Così l'invio mail
 * sincrono esce dalla transazione di dominio: un intoppo del mailer non può più
 * fare rollback di uno stato già valido.
 */
final class FosterApplicationStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly FosterApplication $application,
        public readonly FosterApplicationStatus $from,
        public readonly FosterApplicationStatus $to,
    ) {
        // Dispatch solo dopo il commit della transazione circostante: la notifica
        // non parte finché lo stato non è persistito in modo definitivo. Usa il
        // flag $afterCommit del trait Queueable (nessuna ridefinizione di proprietà).
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
        $animal = $this->application->animal->name;
        $status = $this->to->getLabel();
        $name = $this->application->user->name;

        return (new MailMessage)
            ->subject(__('fostering.status_changed.subject', ['animal' => $animal]))
            ->greeting(__('fostering.status_changed.greeting', ['name' => $name]))
            ->line(__('fostering.status_changed.line', [
                'animal' => $animal,
                'status' => $status,
            ]))
            ->action(
                __('fostering.status_changed.action'),
                url('/account'),
            )
            ->salutation(__('fostering.status_changed.salutation'));
    }

    /**
     * Payload persistito sul canale database: consumato dalla UI notifiche del
     * pannello affidatario.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $animal = $this->application->animal->name;

        return [
            'foster_application_id' => $this->application->getKey(),
            'animal' => $animal,
            'from' => $this->from->value,
            'to' => $this->to->value,
            'message' => __('fostering.status_changed.database', [
                'animal' => $animal,
                'status' => $this->to->getLabel(),
            ]),
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
