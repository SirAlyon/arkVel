<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\MedicalRecord;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Promemoria allo STAFF VERIFICATO dell'ENTE che una voce della cartella sanitaria
 * di un loro animale è in scadenza (o già scaduta): richiamo vaccino, sverminazione,
 * controllo programmato.
 *
 * È emessa dal comando arkvel:send-medical-reminders (schedulato) verso i membri
 * verificati dell'ente proprietario dell'animale. Fa da specchio a
 * NewFosterApplicationReceived. Canali: mail (testo tradotto) + database (alimenta
 * la campanella notifiche dello staff nel pannello shelter).
 *
 * Robustezza: accodata (ShouldQueue) e dispatchata SOLO DOPO il commit
 * ($afterCommit), coerente con le altre notifiche del progetto; il comando marca
 * reminder_sent_at solo dopo Notification::send, così un intoppo non lascia la voce
 * "notificata" senza averlo davvero fatto.
 *
 * Il link punta alla dashboard dell'ente nel pannello shelter; se la rotta non
 * fosse registrata (test/refactor) ricade sulla base del pannello senza sollevare.
 */
final class MedicalReminderDue extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly MedicalRecord $record,
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
        $animal = $this->record->animal->name;
        $type = $this->record->type->getLabel();
        $due = $this->record->next_due_at?->toDateString() ?? '';

        return (new MailMessage)
            ->subject(__('medical.reminder.subject', ['animal' => $animal]))
            ->greeting(__('medical.reminder.greeting'))
            ->line(__('medical.reminder.line', [
                'type' => $type,
                'animal' => $animal,
                'due' => $due,
            ]))
            ->action(
                __('medical.reminder.action'),
                $this->shelterUrl(),
            )
            ->salutation(__('medical.reminder.salutation'));
    }

    /**
     * Payload persistito sul canale database: consumato dalla campanella notifiche
     * dello staff (pannello shelter).
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $animal = $this->record->animal->name;
        $type = $this->record->type->getLabel();
        $due = $this->record->next_due_at?->toDateString() ?? '';

        return [
            'medical_record_id' => $this->record->getKey(),
            'animal_id' => $this->record->animal_id,
            'organization_id' => $this->record->animal?->organization_id,
            'animal' => $animal,
            'type' => $this->record->type->value,
            'next_due_at' => $this->record->next_due_at?->toIso8601String(),
            'message' => __('medical.reminder.database', [
                'type' => $type,
                'animal' => $animal,
                'due' => $due,
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
     * Link best-effort alla dashboard dell'ente. Se la rotta non fosse registrata
     * (test/refactor) ricade sulla base del pannello shelter, senza mai sollevare.
     */
    private function shelterUrl(): string
    {
        $organization = $this->record->animal?->organization;

        try {
            if ($organization !== null) {
                return route('filament.shelter.pages.dashboard', ['tenant' => $organization->slug]);
            }
        } catch (\Throwable) {
            // Ricade sulla base del pannello.
        }

        return url('/manage');
    }
}
