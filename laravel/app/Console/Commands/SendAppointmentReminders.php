<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Notifications\AppointmentReminder;
use Illuminate\Console\Command;

/**
 * Promemoria appuntamenti: invia al richiedente un avviso per ogni appuntamento
 * CONFERMATO in arrivo entro N ore.
 *
 * Schedulato (orario, vedi routes/console.php). Idempotente per design: lo scope
 * Appointment::dueForReminder considera solo gli appuntamenti confermati con
 * reminder_sent_at null nella finestra [now, now+N ore], e la marcatura avviene
 * DOPO l'invio; una seconda esecuzione nella stessa finestra non re-invia nulla.
 */
final class SendAppointmentReminders extends Command
{
    protected $signature = 'arkvel:send-appointment-reminders {--hours=24 : Finestra in ore entro cui un appuntamento è promemorizzato}';

    protected $description = 'Invia ai richiedenti i promemoria degli appuntamenti confermati in arrivo';

    public function handle(): int
    {
        $withinHours = (int) $this->option('hours');

        $appointments = Appointment::query()
            ->dueForReminder($withinHours)
            ->with(['animal', 'user'])
            ->get();

        $sent = 0;

        foreach ($appointments as $appointment) {
            // La notifica è accodata after-commit: parte dopo il commit del save.
            $appointment->user->notify(new AppointmentReminder($appointment));

            $appointment->reminder_sent_at = now();
            $appointment->save();

            $sent++;
        }

        $this->info(__('appointments.reminder.command_summary', ['count' => $sent]));

        return self::SUCCESS;
    }
}
