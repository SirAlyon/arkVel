<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\FosterApplication;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifica ai membri VERIFICATI dell'ENTE che è arrivata una nuova richiesta di
 * affido per uno dei loro animali.
 *
 * È emessa da App\Actions\Fostering\SubmitFosterApplication subito dopo la
 * nascita della richiesta, verso i membri verificati dell'ente (owner + staff).
 * Fa da specchio a NewApplicationReceived (flusso adozione). Canali: mail (testo
 * tradotto) + database (alimenta la UI notifiche dello staff).
 *
 * Il link punta alla dashboard dell'ente nel pannello shelter. Se la rotta non
 * fosse ancora registrata (contesti di test/futuri refactor) ricadiamo sulla
 * base del pannello, senza mai sollevare eccezioni.
 *
 * Robustezza: accodata (ShouldQueue) e dispatchata SOLO DOPO il commit
 * ($afterCommit), così l'invio mail sincrono esce dalla transazione di
 * SubmitFosterApplication: un intoppo del mailer non fa più rollback della
 * richiesta.
 */
final class NewFosterApplicationReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly FosterApplication $application,
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
        $carer = $this->application->user->name;

        return (new MailMessage)
            ->subject(__('fostering.new_received.subject', ['animal' => $animal]))
            ->greeting(__('fostering.new_received.greeting'))
            ->line(__('fostering.new_received.line', [
                'carer' => $carer,
                'animal' => $animal,
            ]))
            ->action(
                __('fostering.new_received.action'),
                $this->shelterUrl(),
            )
            ->salutation(__('fostering.new_received.salutation'));
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
        $carer = $this->application->user->name;

        return [
            'foster_application_id' => $this->application->getKey(),
            'animal_id' => $this->application->animal_id,
            'organization_id' => $this->application->organization_id,
            'animal' => $animal,
            'carer' => $carer,
            'message' => __('fostering.new_received.database', [
                'carer' => $carer,
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
     * Link best-effort alla dashboard dell'ente. Se la rotta non fosse ancora
     * registrata (contesti di test/futuri refactor) ricadiamo sulla base del
     * pannello shelter, senza mai sollevare eccezioni.
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
