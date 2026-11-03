<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Promemoria al RICHIEDENTE che il suo appuntamento (già confermato) è imminente.
 *
 * È emessa dal comando arkvel:send-appointment-reminders (schedulato) verso il
 * proprietario dell'appuntamento ($appointment->user). Canali: mail (testo tradotto)
 * + database (UI notifiche account). Accodata (ShouldQueue) e dispatchata SOLO DOPO
 * il commit ($afterCommit), coerente con le altre notifiche del progetto.
 */
final class AppointmentReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Appointment $appointment,
    ) {
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
        $animal = $this->appointment->animal->name;
        $name = $this->appointment->user->name;
        $type = $this->appointment->type->getLabel();
        $when = $this->appointment->scheduled_at->toDateTimeString();

        return (new MailMessage)
            ->subject(__('appointments.reminder.subject', ['animal' => $animal]))
            ->greeting(__('appointments.reminder.greeting', ['name' => $name]))
            ->line(__('appointments.reminder.line', [
                'type' => $type,
                'animal' => $animal,
                'when' => $when,
            ]))
            ->action(
                __('appointments.reminder.action'),
                url('/account'),
            )
            ->salutation(__('appointments.reminder.salutation'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $animal = $this->appointment->animal->name;
        $type = $this->appointment->type->getLabel();
        $when = $this->appointment->scheduled_at->toDateTimeString();

        return [
            'appointment_id' => $this->appointment->getKey(),
            'animal_id' => $this->appointment->animal_id,
            'organization_id' => $this->appointment->organization_id,
            'animal' => $animal,
            'type' => $this->appointment->type->value,
            'scheduled_at' => $this->appointment->scheduled_at->toIso8601String(),
            'message' => __('appointments.reminder.database', [
                'type' => $type,
                'animal' => $animal,
                'when' => $when,
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
