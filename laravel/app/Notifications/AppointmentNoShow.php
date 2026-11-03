<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifica al RICHIEDENTE che è stata registrata la sua assenza a un appuntamento
 * confermato.
 *
 * È emessa da App\Actions\Appointments\MarkAppointmentNoShow verso il proprietario
 * dell'appuntamento. Canali: mail (testo tradotto) + database. Accodata
 * (ShouldQueue) e dispatchata SOLO DOPO il commit ($afterCommit) della transazione.
 */
final class AppointmentNoShow extends Notification implements ShouldQueue
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
        $when = $this->appointment->scheduled_at->toDateTimeString();

        return (new MailMessage)
            ->subject(__('appointments.no_show.subject', ['animal' => $animal]))
            ->greeting(__('appointments.no_show.greeting', ['name' => $name]))
            ->line(__('appointments.no_show.line', [
                'animal' => $animal,
                'when' => $when,
            ]))
            ->action(
                __('appointments.no_show.action'),
                url('/account'),
            )
            ->salutation(__('appointments.no_show.salutation'));
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
            'message' => __('appointments.no_show.database', ['animal' => $animal]),
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
