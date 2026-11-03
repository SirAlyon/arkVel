<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Appointment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifica ai membri VERIFICATI dell'ENTE che è stato richiesto un nuovo
 * appuntamento (visita/colloquio/controllo domiciliare) su uno dei loro animali.
 *
 * È emessa da App\Actions\Appointments\RequestAppointment subito dopo la nascita
 * dell'appuntamento, verso i membri verificati dell'ente (owner + staff). Fa da
 * specchio a NewFosterApplicationReceived. Canali: mail (testo tradotto) +
 * database (alimenta la UI notifiche dello staff).
 *
 * Robustezza: accodata (ShouldQueue) e dispatchata SOLO DOPO il commit
 * ($afterCommit) della transazione di RequestAppointment.
 */
final class NewAppointmentRequested extends Notification implements ShouldQueue
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
        $requester = $this->appointment->user->name;
        $type = $this->appointment->type->getLabel();
        $when = $this->appointment->scheduled_at->toDateTimeString();

        return (new MailMessage)
            ->subject(__('appointments.new_requested.subject', ['animal' => $animal]))
            ->greeting(__('appointments.new_requested.greeting'))
            ->line(__('appointments.new_requested.line', [
                'requester' => $requester,
                'animal' => $animal,
                'type' => $type,
                'when' => $when,
            ]))
            ->action(
                __('appointments.new_requested.action'),
                $this->shelterUrl(),
            )
            ->salutation(__('appointments.new_requested.salutation'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $animal = $this->appointment->animal->name;
        $requester = $this->appointment->user->name;
        $type = $this->appointment->type->getLabel();

        return [
            'appointment_id' => $this->appointment->getKey(),
            'animal_id' => $this->appointment->animal_id,
            'organization_id' => $this->appointment->organization_id,
            'animal' => $animal,
            'requester' => $requester,
            'type' => $this->appointment->type->value,
            'scheduled_at' => $this->appointment->scheduled_at->toIso8601String(),
            'message' => __('appointments.new_requested.database', [
                'requester' => $requester,
                'animal' => $animal,
                'type' => $type,
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

    /**
     * Link best-effort alla dashboard dell'ente (pannello shelter). Se la rotta non
     * fosse ancora registrata ricadiamo sulla base del pannello, senza mai
     * sollevare eccezioni.
     */
    private function shelterUrl(): string
    {
        $organization = $this->appointment->organization;

        try {
            return route('filament.shelter.pages.dashboard', ['tenant' => $organization->slug]);
        } catch (\Throwable) {
            return url('/manage');
        }
    }
}
