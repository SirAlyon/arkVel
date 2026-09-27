<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Appointment;
use App\Support\Calendar\AppointmentCalendarExport;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifica al RICHIEDENTE che l'ente ha spostato il suo appuntamento a un nuovo
 * orario.
 *
 * È emessa da App\Actions\Appointments\RescheduleAppointment verso il proprietario
 * dell'appuntamento. Canali: mail (testo tradotto) + database (UI notifiche account).
 * Accodata (ShouldQueue) e dispatchata SOLO DOPO il commit ($afterCommit) della
 * transazione. Porta con sé il vecchio orario per un messaggio parlante.
 */
final class AppointmentRescheduled extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Appointment $appointment,
        public readonly CarbonInterface $previousScheduledAt,
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
        $from = $this->previousScheduledAt->toDateTimeString();
        $to = $this->appointment->scheduled_at->toDateTimeString();

        return (new MailMessage)
            ->subject(__('appointments.rescheduled.subject', ['animal' => $animal]))
            ->greeting(__('appointments.rescheduled.greeting', ['name' => $name]))
            ->line(__('appointments.rescheduled.line', [
                'animal' => $animal,
                'from' => $from,
                'to' => $to,
            ]))
            ->action(
                __('appointments.rescheduled.action'),
                url('/account'),
            )
            // Allega il singolo evento .ics aggiornato (nuovo orario, PII minimizzata):
            // l'UID stabile fa sì che il client AGGIORNI l'evento invece di duplicarlo.
            ->attachData(
                app(AppointmentCalendarExport::class)->single($this->appointment),
                'appointment.ics',
                ['mime' => 'text/calendar'],
            )
            ->salutation(__('appointments.rescheduled.salutation'));
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
            'previous_scheduled_at' => $this->previousScheduledAt->toIso8601String(),
            'scheduled_at' => $this->appointment->scheduled_at->toIso8601String(),
            'message' => __('appointments.rescheduled.database', [
                'animal' => $animal,
                'to' => $this->appointment->scheduled_at->toDateTimeString(),
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
