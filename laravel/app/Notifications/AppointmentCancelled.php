<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifica alla CONTROPARTE che un appuntamento è stato annullato.
 *
 * È emessa da App\Actions\Appointments\CancelAppointment verso la parte che NON ha
 * annullato: se annulla il richiedente → notifica allo staff; se annulla lo staff
 * → notifica al richiedente. Il testo è neutro rispetto al destinatario. Canali:
 * mail (testo tradotto) + database. Accodata (ShouldQueue) e dispatchata SOLO DOPO
 * il commit ($afterCommit) della transazione.
 */
final class AppointmentCancelled extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Appointment $appointment,
        public readonly ?string $reason = null,
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
        $when = $this->appointment->scheduled_at->toDateTimeString();

        $mail = (new MailMessage)
            ->subject(__('appointments.cancelled.subject', ['animal' => $animal]))
            ->greeting(__('appointments.cancelled.greeting'))
            ->line(__('appointments.cancelled.line', [
                'animal' => $animal,
                'when' => $when,
            ]));

        if ($this->reason !== null && $this->reason !== '') {
            $mail->line(__('appointments.cancelled.reason', ['reason' => $this->reason]));
        }

        return $mail
            ->action(
                __('appointments.cancelled.action'),
                url('/account'),
            )
            ->salutation(__('appointments.cancelled.salutation'));
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
            'reason' => $this->reason,
            'message' => __('appointments.cancelled.database', [
                'animal' => $animal,
                'when' => $this->appointment->scheduled_at->toDateTimeString(),
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
