<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\AdoptionApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifica ai membri VERIFICATI dell'ENTE che è arrivata una nuova candidatura
 * per uno dei loro animali.
 *
 * È emessa da App\Actions\Applications\SubmitApplication subito dopo la nascita
 * della candidatura, verso i membri verificati dell'ente (owner + staff). Fa da
 * specchio ad ApplicationStatusChanged (che invece notifica l'adottante).
 * Canali: mail (testo tradotto) + database (alimenta la UI notifiche dello staff).
 *
 * Il link punta all'area di gestione dell'ente nel pannello shelter. L'area
 * "candidature" vera e propria arriva nella slice 9: fino ad allora usiamo un
 * link best-effort alla dashboard del tenant, calcolato senza dipendere dalla
 * registrazione di rotte non ancora esistenti (nessun hard-fail).
 */
final class NewApplicationReceived extends Notification
{
    use Queueable;

    public function __construct(
        public readonly AdoptionApplication $application,
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
        $adopter = $this->application->user->name;

        return (new MailMessage)
            ->subject(__('applications.new_received.subject', ['animal' => $animal]))
            ->greeting(__('applications.new_received.greeting'))
            ->line(__('applications.new_received.line', [
                'adopter' => $adopter,
                'animal' => $animal,
            ]))
            ->action(
                __('applications.new_received.action'),
                $this->shelterUrl(),
            )
            ->salutation(__('applications.new_received.salutation'));
    }

    /**
     * Payload persistito sul canale database: consumato dalla futura UI notifiche
     * dello staff (pannello shelter).
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $animal = $this->application->animal->name;
        $adopter = $this->application->user->name;

        return [
            'application_id' => $this->application->getKey(),
            'animal_id' => $this->application->animal_id,
            'organization_id' => $this->application->organization_id,
            'animal' => $animal,
            'adopter' => $adopter,
            'message' => __('applications.new_received.database', [
                'adopter' => $adopter,
                'animal' => $animal,
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
     * Link best-effort all'area di gestione dell'ente. La dashboard del tenant
     * esiste (`filament.shelter.pages.dashboard` con {tenant:slug}); se la rotta
     * non fosse ancora registrata (contesti di test/futuri refactor) ricadiamo
     * sulla base del pannello shelter, senza mai sollevare eccezioni.
     */
    private function shelterUrl(): string
    {
        $organization = $this->application->organization;

        try {
            return route('filament.shelter.pages.dashboard', ['tenant' => $organization->slug]);
        } catch (\Throwable) {
            return url('/manage');
        }
    }
}
