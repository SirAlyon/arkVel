<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\AdoptionApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifica allo STAFF dell'ENTE che l'adottante ha RITIRATO la sua candidatura.
 *
 * È emessa da App\Actions\Applications\WithdrawApplication dopo il ritiro (e
 * l'eventuale rilascio dell'animale), verso i destinatari dell'ente: l'operatore
 * in carico (assignedTo) se assegnato, altrimenti i membri verificati (owner +
 * staff). Fa da specchio a NewApplicationReceived: chiude il ciclo di vita della
 * candidatura anche lato ente. Canali: mail (testo tradotto) + database
 * (alimenta la UI notifiche dello staff nel pannello shelter).
 *
 * Robustezza (slice 11): accodata (ShouldQueue) e dispatchata SOLO DOPO il commit
 * ($afterCommit), così l'invio mail sincrono esce dalla transazione di
 * WithdrawApplication: un intoppo del mailer non fa più rollback del ritiro.
 */
final class ApplicationWithdrawn extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly AdoptionApplication $application,
    ) {
        // Dispatch solo dopo il commit della transazione circostante (flag
        // $afterCommit del trait Queueable, nessuna ridefinizione di proprietà).
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
        $adopter = $this->application->user->name;

        return (new MailMessage)
            ->subject(__('applications.withdrawn.subject', ['animal' => $animal]))
            ->greeting(__('applications.withdrawn.greeting'))
            ->line(__('applications.withdrawn.line', [
                'adopter' => $adopter,
                'animal' => $animal,
            ]))
            ->action(
                __('applications.withdrawn.action'),
                $this->shelterUrl(),
            )
            ->salutation(__('applications.withdrawn.salutation'));
    }

    /**
     * Payload persistito sul canale database: consumato dalla UI notifiche dello
     * staff (pannello shelter).
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
            'message' => __('applications.withdrawn.database', [
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
     * Link best-effort alla board candidature dell'ente. Se la rotta non fosse
     * ancora registrata (contesti di test/futuri refactor) ricadiamo sulla base
     * del pannello shelter, senza mai sollevare eccezioni.
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
