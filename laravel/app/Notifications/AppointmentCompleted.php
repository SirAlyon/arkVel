<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifica al RICHIEDENTE che il suo appuntamento è stato completato.
 *
 * È emessa da App\Actions\Appointments\CompleteAppointment verso il proprietario
 * dell'appuntamento. Canali: mail (testo tradotto) + database. Accodata
 * (ShouldQueue) e dispatchata SOLO DOPO il commit ($afterCommit) della transazione.
 */
final class AppointmentCompleted extends Notification implements ShouldQueue
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

        return (new MailMessage)
            ->subject(__('appointments.completed.subject', ['animal' => $animal]))
            ->greeting(__('appointments.completed.greeting', ['name' => $name]))
            ->line(__('appointments.completed.line', ['animal' => $animal]))
            ->action(
                __('appointments.completed.action'),
                url('/account'),
            )
            ->salutation(__('appointments.completed.salutation'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $animal = $this->appointment->animal->name;

        return [
            'appointment_id' => $this->appointment->getKey(),
            'animal_id' => $this->appointment->animal_id,
            'organization_id' => $this->appointment->organization_id,
            'animal' => $animal,
            'scheduled_at' => $this->appointment->scheduled_at->toIso8601String(),
            'message' => __('appointments.completed.database', ['animal' => $animal]),
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
