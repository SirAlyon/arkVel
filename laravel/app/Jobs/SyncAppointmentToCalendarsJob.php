<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\CalendarConnectionStatus;
use App\Models\Appointment;
use App\Models\AppointmentExternalEvent;
use App\Models\CalendarConnection;
use App\Observers\AppointmentObserver;
use App\Support\Calendar\AppointmentEventMapper;
use App\Support\Calendar\CalendarEventData;
use App\Support\Calendar\CalendarProviderRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sincronizza UN appuntamento verso TUTTE le connessioni attive del suo ente.
 *
 * È il motore della pipeline di push: l'{@see AppointmentObserver}
 * lo accoda dopo ogni mutazione (afterCommit), passando id + operazione. Il job non
 * conosce i provider concreti: risolve ognuno dal {@see CalendarProviderRegistry}
 * (Dependency Inversion) e consuma il {@see CalendarEventData}
 * prodotto dal mapper (unica fonte di verità dei dati che escono).
 *
 * IDEMPOTENZA: per ogni (appuntamento, connessione) esiste al più un
 * {@see AppointmentExternalEvent}. In upsert riusa l'eventuale mappatura (update
 * remoto invece di duplicare). Rieseguire il job con lo stesso stato non produce
 * doppioni.
 *
 * CANCELLAZIONE: quando un appuntamento viene eliminato, la FK cascade rimuove le
 * mappature PRIMA che questo job (afterCommit) parta — quindi non potremmo più
 * leggerle. Perciò l'{@see AppointmentObserver} le fotografa in `deleting` e ce le
 * passa nel payload ($deletions): il job cancella l'evento remoto usando quei
 * riferimenti, senza dipendere né dall'appuntamento né dalla mappatura (ormai spariti).
 *
 * ISOLAMENTO DEI GUASTI: ogni connessione è avvolta nel proprio try/catch. Se un
 * provider fallisce, la connessione passa a status=Error con last_error e si prosegue
 * con le altre: una connessione rotta non blocca il push verso le sane.
 *
 * Passiamo l'ID (non il model) così il job lavora sempre sullo stato CORRENTE al
 * momento dell'esecuzione, coerente con afterCommit.
 */
class SyncAppointmentToCalendarsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const string OP_UPSERT = 'upsert';

    public const string OP_DELETE = 'delete';

    /**
     * @param  list<array{connection_id: int, external_id: string, etag: string|null}>  $deletions
     *                                                                                              Riferimenti remoti fotografati prima del cascade (solo per OP_DELETE).
     */
    public function __construct(
        public readonly int $appointmentId,
        public readonly string $operation,
        public readonly array $deletions = [],
    ) {}

    public function handle(
        CalendarProviderRegistry $registry,
        AppointmentEventMapper $mapper,
    ): void {
        // Cancellazione: l'appuntamento e le mappature non esistono più (cascade). Si
        // lavora esclusivamente sui riferimenti nel payload.
        if ($this->operation === self::OP_DELETE) {
            $this->deleteRemoteEvents($registry);

            return;
        }

        $appointment = Appointment::query()
            ->with(['animal', 'organization'])
            ->find($this->appointmentId);

        // L'appuntamento potrebbe essere sparito fra dispatch ed esecuzione: per
        // l'upsert non c'è nulla da spingere.
        if ($appointment === null) {
            return;
        }

        // syncable() = Active + Error: una connessione in errore transitorio viene
        // RIPROVATA e, se il push riesce, torna Active (self-healing). Solo le
        // Disconnected restano escluse.
        $connections = $appointment->organization
            ->calendarConnections()
            ->syncable()
            ->get();

        foreach ($connections as $connection) {
            try {
                $this->upsertConnection($registry, $mapper, $appointment, $connection);
            } catch (Throwable $exception) {
                $this->markConnectionErrored($connection, $exception);
            }
        }
    }

    /**
     * Cancella gli eventi remoti dai riferimenti fotografati nel payload. Ogni
     * riferimento è isolato: il fallimento su una connessione non blocca le altre. La
     * connessione può essere sparita nel frattempo (disconnessa) → si salta.
     */
    private function deleteRemoteEvents(CalendarProviderRegistry $registry): void
    {
        foreach ($this->deletions as $deletion) {
            $connection = CalendarConnection::query()->find($deletion['connection_id']);

            if ($connection === null) {
                continue;
            }

            try {
                $registry->forConnection($connection)
                    ->deleteEvent($connection, $deletion['external_id'], $deletion['etag']);

                $this->markConnectionSynced($connection);
            } catch (Throwable $exception) {
                $this->markConnectionErrored($connection, $exception);
            }
        }
    }

    /**
     * Push (upsert) verso UNA connessione. Isolata così il chiamante può intercettare
     * il fallimento della singola connessione senza abortire le altre.
     */
    private function upsertConnection(
        CalendarProviderRegistry $registry,
        AppointmentEventMapper $mapper,
        Appointment $appointment,
        CalendarConnection $connection,
    ): void {
        $provider = $registry->forConnection($connection);

        $mapping = AppointmentExternalEvent::query()
            ->where('appointment_id', $appointment->getKey())
            ->where('calendar_connection_id', $connection->getKey())
            ->first();

        // Crea o aggiorna l'evento remoto e persiste la mappatura.
        $event = $mapper->toEventData($appointment);

        $pushed = $provider->pushEvent(
            $connection,
            $event,
            $mapping?->external_event_id,
            $mapping?->etag,
        );

        AppointmentExternalEvent::query()->updateOrCreate(
            [
                'appointment_id' => $appointment->getKey(),
                'calendar_connection_id' => $connection->getKey(),
            ],
            [
                'external_event_id' => $pushed->externalId,
                'etag' => $pushed->etag,
                'last_pushed_at' => now(),
            ],
        );

        $this->markConnectionSynced($connection);
    }

    /**
     * Segna la connessione come sana dopo un push riuscito: aggiorna last_synced_at e,
     * se era in Error, la riporta ad Active azzerando last_error.
     */
    private function markConnectionSynced(CalendarConnection $connection): void
    {
        $connection->forceFill([
            'status' => CalendarConnectionStatus::Active,
            'last_error' => null,
            'last_synced_at' => now(),
        ])->save();
    }

    /**
     * Marca la connessione come in errore (con dettaglio) senza propagare: gli altri
     * provider devono comunque ricevere il push.
     */
    private function markConnectionErrored(CalendarConnection $connection, Throwable $exception): void
    {
        // Il dettaglio COMPLETO va solo nel log server-side (non esposto in UI).
        Log::warning('[calendar-sync] push failed for connection', [
            'connection_id' => $connection->getKey(),
            'appointment_id' => $this->appointmentId,
            'operation' => $this->operation,
            'error' => $exception->getMessage(),
        ]);

        // last_error è VISIBILE nel pannello /manage: lo riduciamo a una singola riga
        // troncata per non trasformarlo in un canale di leak. I provider reali
        // (Cal-5/6) devono comunque sollevare eccezioni SENZA segreti/token nel
        // messaggio; qui manteniamo comunque un tetto difensivo.
        $summary = Str::of($exception->getMessage())
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->limit(300)
            ->value();

        $connection->forceFill([
            'status' => CalendarConnectionStatus::Error,
            'last_error' => $summary !== '' ? $summary : class_basename($exception),
        ])->save();
    }
}
