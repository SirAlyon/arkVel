<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\MedicalRecord;
use App\Notifications\MedicalReminderDue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

/**
 * Scadenzario sanitario: invia allo staff verificato dell'ente un promemoria per
 * ogni voce della cartella sanitaria in scadenza (o già scaduta) entro N giorni.
 *
 * Schedulato (giornaliero, vedi routes/console.php). Idempotente per design: lo
 * scope MedicalRecord::dueForReminder considera solo le voci con reminder_sent_at
 * null, e la marcatura avviene DOPO l'invio; una seconda esecuzione nella stessa
 * finestra non re-invia nulla. Sottile: la logica di dominio (predicato di
 * scadenza) vive nello scope del model, il fan-out delle notifiche nella notifica
 * accodata; qui c'è solo l'orchestrazione (query → send → mark → log).
 */
final class SendMedicalReminders extends Command
{
    protected $signature = 'arkvel:send-medical-reminders {--days=7 : Finestra in giorni entro cui una scadenza è promemorizzata}';

    protected $description = 'Invia allo staff dell\'ente i promemoria delle scadenze sanitarie in arrivo';

    public function handle(): int
    {
        $withinDays = (int) $this->option('days');

        $records = MedicalRecord::query()
            ->dueForReminder($withinDays)
            ->with('animal.organization')
            ->get();

        $sent = 0;

        foreach ($records as $record) {
            $organization = $record->animal?->organization;

            // Voce senza animale/ente: nessun destinatario possibile, la saltiamo
            // senza marcarla (potrà essere promemorizzata quando l'ente sarà noto).
            if ($organization === null) {
                continue;
            }

            // verifiedStaff() filtra per verified=true (owner + staff). Destinatari
            // vuoti → Notification::send è un no-op sicuro. Marchiamo SOLO dopo
            // l'invio: niente "notificata senza notifica".
            Notification::send(
                $organization->verifiedStaff()->get(),
                new MedicalReminderDue($record),
            );

            $record->reminder_sent_at = now();
            $record->save();

            $sent++;
        }

        $this->info(__('medical.reminder.command_summary', ['count' => $sent]));

        return self::SUCCESS;
    }
}
