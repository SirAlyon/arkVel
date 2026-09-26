<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\ApplicationStatus;
use App\Models\AdoptionApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifica all'ADOTTANTE che lo stato della sua candidatura è cambiato.
 *
 * È emessa da App\Actions\Applications\AdvanceApplication (transizione staff)
 * verso il proprietario della candidatura. Trasporta la coppia from/to come
 * enum per tenere UI/messaggi allineati all'unica fonte di verità delle label.
 * Canali: mail (testo tradotto) + database (alimenta la UI notifiche adottante,
 * slice 11).
 */
final class ApplicationStatusChanged extends Notification
{
    use Queueable;

    public function __construct(
        public readonly AdoptionApplication $application,
        public readonly ApplicationStatus $from,
        public readonly ApplicationStatus $to,
    ) {}

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
            ->subject(__('applications.status_changed.subject', ['animal' => $animal]))
            ->greeting(__('applications.status_changed.greeting', ['name' => $name]))
            ->line(__('applications.status_changed.line', [
                'animal' => $animal,
                'status' => $status,
            ]))
            ->action(
                __('applications.status_changed.action'),
                url('/account'),
            )
            ->salutation(__('applications.status_changed.salutation'));
    }

    /**
     * Payload persistito sul canale database: consumato dalla UI notifiche del
     * pannello adottante (slice 11).
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $animal = $this->application->animal->name;

        return [
            'application_id' => $this->application->getKey(),
            'animal' => $animal,
            'from' => $this->from->value,
            'to' => $this->to->value,
            'message' => __('applications.status_changed.database', [
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
