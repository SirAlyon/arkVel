<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\CalendarConnectionStatus;
use App\Enums\CalendarSyncDirection;
use App\Models\CalendarConnection;
use App\Support\Calendar\CalendarReconciler;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

/**
 * Ramo PULL della sincronizzazione bidirezionale (Cal-7): tira le modifiche fatte sui
 * calendari ESTERNI e le riconcilia dentro gli appuntamenti ArkVel via
 * {@see CalendarReconciler} (riprogrammazioni/annullamenti dal calendario dello staff).
 *
 * Schedulato (ogni 15 min, vedi routes/console.php), con withoutOverlapping. Processa solo
 * le connessioni ATTIVE e bidirezionali (direction=Both): le push-only escono soltanto.
 * ISOLAMENTO: ogni connessione è avvolta in try/catch — una connessione rotta viene marcata
 * Error e non ferma le altre.
 */
final class SyncCalendarsCommand extends Command
{
    protected $signature = 'arkvel:sync-calendars {--connection= : Riconcilia solo la connessione con questo id}';

    protected $description = 'Tira e riconcilia le modifiche dei calendari esterni (Google/CalDAV) negli appuntamenti';

    public function handle(CalendarReconciler $reconciler): int
    {
        $connections = CalendarConnection::query()
            ->active()
            ->where('direction', CalendarSyncDirection::Both)
            ->when(
                $this->option('connection'),
                fn ($query, $id) => $query->whereKey((int) $id),
            )
            ->get();

        $processed = 0;
        $rescheduled = 0;
        $cancelled = 0;

        foreach ($connections as $connection) {
            try {
                $summary = $reconciler->reconcile($connection);
                $rescheduled += $summary['rescheduled'];
                $cancelled += $summary['cancelled'];
                $processed++;
            } catch (Throwable $exception) {
                // Guasto dell'intera connessione: marca Error (dettaglio troncato/sanitizzato,
                // completo solo nei log) e prosegui con le altre.
                $connection->forceFill([
                    'status' => CalendarConnectionStatus::Error,
                    'last_error' => Str::of($exception->getMessage())->replaceMatches('/\s+/', ' ')->trim()->limit(300)->value(),
                ])->save();

                $this->warn("Connessione #{$connection->getKey()}: sync fallito ({$exception->getMessage()}).");
            }
        }

        $this->info(__('calendar.reconcile.command_summary', [
            'connections' => $processed,
            'rescheduled' => $rescheduled,
            'cancelled' => $cancelled,
        ]));

        return self::SUCCESS;
    }
}
