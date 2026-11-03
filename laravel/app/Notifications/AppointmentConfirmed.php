<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Appointment;
use App\Support\Calendar\AppointmentCalendarExport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifica al RICHIEDENTE che l'ente ha confermato il suo appuntamento.
 *
 * È emessa da App\Actions\Appointments\ConfirmAppointment verso il proprietario
 * dell'appuntamento. Canali: mail (testo tradotto) + database (UI notifiche
 * account). Accodata (ShouldQueue) e dispatchata SOLO DOPO il commit
 * ($afterCommit) della transazione.
 */
final class AppointmentConfirmed extends Notification implements ShouldQueue
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
            ->subject(__('appointments.confirmed.subject', ['animal' => $animal]))
            ->greeting(__('appointments.confirmed.greeting', ['name' => $name]))
            ->line(__('appointments.confirmed.line', [
                'animal' => $animal,
                'when' => $when,
            ]))
            ->action(
                __('appointments.confirmed.action'),
                url('/account'),
            )
            // Allega il singolo evento .ics (PII minimizzata): i client di calendario
            // lo rilevano e offrono "aggiungi al calendario". Il mapping/minimizzazione
            // vive tutto in AppointmentCalendarExport.
            ->attachData(
                app(AppointmentCalendarExport::class)->single($this->appointment),
                'appointment.ics',
                ['mime' => 'text/calendar'],
            )
            ->salutation(__('appointments.confirmed.salutation'));
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
            'message' => __('appointments.confirmed.database', [
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
