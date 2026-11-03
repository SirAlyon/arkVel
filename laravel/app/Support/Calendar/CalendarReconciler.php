<?php

declare(strict_types=1);

namespace App\Support\Calendar;

use App\Actions\Appointments\CancelAppointment;
use App\Actions\Appointments\RescheduleAppointment;
use App\Enums\AppointmentStatus;
use App\Enums\CalendarConnectionStatus;
use App\Jobs\SyncAppointmentToCalendarsJob;
use App\Models\Appointment;
use App\Models\AppointmentExternalEvent;
use App\Models\CalendarConnection;
use App\Models\User;
use App\Support\Calendar\Providers\PulledEvent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Riconcilia le modifiche fatte su un calendario ESTERNO dentro gli appuntamenti ArkVel
 * (Cal-7, ramo pull della sincronizzazione bidirezionale). È il gemello del push
 * ({@see SyncAppointmentToCalendarsJob}): il push porta fuori le mutazioni ArkVel,
 * il reconciler porta dentro quelle esterne.
 *
 * ARKVEL È AUTORITATIVO. Non tocchiamo mai la colonna di stato/orario a mano: le modifiche
 * esterne passano SEMPRE dalle Action di dominio ({@see RescheduleAppointment} /
 * {@see CancelAppointment}), così restano valide tutte le guardie (slot futuro, overlap,
 * transizioni legali), l'activity log e le notifiche alla controparte. Una modifica esterna
 * che violerebbe una guardia (slot nel passato, overlap, appuntamento terminale) viene
 * SCARTATA (loggata), non forzata: il calendario esterno non può mettere ArkVel in uno stato
 * illegale.
 *
 * CONVERGENZA / NIENTE PING-PONG. Agiamo SOLO quando il valore esterno DIFFERISCE da quello
 * ArkVel (start diverso, oppure status cancelled su appuntamento ancora attivo). Quando il
 * reconciler riprogramma, l'observer ri-spinge lo stesso valore sui calendari; il pull
 * successivo vedrà start == scheduled_at → no-op. Il ciclo converge in un giro, senza
 * rimbalzi. Un evento che combacia già aggiorna solo l'etag della mappatura.
 *
 * SYSTEM ACTOR. Le Action richiedono uno {@see User} attore (per log/notifica): usiamo il
 * primo membro verificato dell'ente (staff/owner). Senza membri verificati la connessione è
 * SALTATA (non c'è chi possa "causare" la modifica): niente riconciliazione, la connessione
 * resta invariata.
 *
 * ISOLAMENTO DEI GUASTI. Ogni singola voce è avvolta in try/catch: un'eccezione di dominio
 * (o qualunque errore) su una voce la salta senza abortire la connessione. Il chiamante
 * (comando) isola a sua volta l'intera connessione, così una connessione rotta non ferma le
 * altre.
 */
final class CalendarReconciler
{
    public function __construct(
        private readonly CalendarProviderRegistry $registry,
        private readonly RescheduleAppointment $reschedule,
        private readonly CancelAppointment $cancel,
    ) {}

    /**
     * Riconcilia le modifiche esterne di UNA connessione. Ritorna un piccolo riepilogo dei
     * conteggi (rescheduled/cancelled/skipped) per il log/summary del comando.
     *
     * @return array{rescheduled: int, cancelled: int, skipped: int}
     */
    public function reconcile(CalendarConnection $connection): array
    {
        $summary = ['rescheduled' => 0, 'cancelled' => 0, 'skipped' => 0];

        // Le Action hanno bisogno di un attore (log/notifica): il primo membro verificato
        // dell'ente. Senza, non c'è chi possa "causare" la modifica → salta la connessione.
        $actor = $connection->organization->verifiedStaff()->first();

        if (! $actor instanceof User) {
            Log::info('[calendar-reconcile] skipped: no verified staff for the organization', [
                'connection_id' => $connection->getKey(),
                'organization_id' => $connection->organization_id,
            ]);

            return $summary;
        }

        $changes = $this->registry->forConnection($connection)->pullChanges($connection);

        // Cancellazioni remote (evento sparito): annulla l'appuntamento se ancora attivo.
        foreach ($changes->deletedExternalIds as $externalId) {
            $this->guard($connection, function () use ($connection, $actor, $externalId, &$summary): void {
                $this->reconcileDeletion($connection, $actor, $externalId, $summary);
            });
        }

        // Eventi creati/modificati: riprogramma o annulla secondo la divergenza col valore
        // ArkVel; altrimenti aggiorna solo l'etag della mappatura.
        foreach ($changes->changed as $event) {
            $this->guard($connection, function () use ($connection, $actor, $event, &$summary): void {
                $this->reconcileChange($connection, $actor, $event, $summary);
            });
        }

        // Persisti l'avanzamento del sync (token + last_synced_at) e riporta la connessione
        // ad Active: un pull riuscito è "sano" quanto un push riuscito.
        $connection->forceFill([
            'sync_token' => $changes->newSyncToken ?? $connection->sync_token,
            'last_synced_at' => now(),
            'status' => CalendarConnectionStatus::Active,
            'last_error' => null,
        ])->save();

        return $summary;
    }

    /**
     * Cancellazione remota: risolve l'appuntamento dalla mappatura (external id) e, se
     * appartiene a QUESTA org ed è ancora attivo, lo annulla; poi rimuove la mappatura
     * (l'evento remoto non esiste più).
     *
     * @param  array{rescheduled: int, cancelled: int, skipped: int}  $summary
     */
    private function reconcileDeletion(
        CalendarConnection $connection,
        User $actor,
        string $externalId,
        array &$summary,
    ): void {
        $mapping = $this->mappingFor($connection, $externalId);

        if ($mapping === null) {
            $summary['skipped']++;

            return;
        }

        $appointment = $mapping->appointment;

        if ($this->belongsToConnectionOrg($appointment, $connection) && $this->isActive($appointment)) {
            $this->cancel->handle($appointment, $actor, __('calendar.reconcile.cancelled_externally'));
            $summary['cancelled']++;
        } else {
            $summary['skipped']++;
        }

        // L'evento remoto è sparito: la mappatura non serve più.
        $mapping->delete();
    }

    /**
     * Evento creato/modificato: risolve l'appuntamento (preferendo l'id ricavato
     * dall'evento, altrimenti la mappatura per external id) e applica la divergenza.
     *
     * @param  array{rescheduled: int, cancelled: int, skipped: int}  $summary
     */
    private function reconcileChange(
        CalendarConnection $connection,
        User $actor,
        PulledEvent $event,
        array &$summary,
    ): void {
        $mapping = $this->mappingFor($connection, $event->externalId);
        $appointment = $this->resolveAppointment($event, $mapping);

        // Nessun appuntamento risolvibile, o di un'ALTRA org (tenant isolation): salta.
        if ($appointment === null || ! $this->belongsToConnectionOrg($appointment, $connection)) {
            $summary['skipped']++;

            return;
        }

        if ($event->isCancelled()) {
            if ($this->isActive($appointment)) {
                $this->cancel->handle($appointment, $actor, __('calendar.reconcile.cancelled_externally'));
                $summary['cancelled']++;
            } else {
                $summary['skipped']++;
            }
        } elseif ($this->needsReschedule($event, $appointment)) {
            // start non nullo E diverso E appuntamento riprogrammabile (guardie dell'Action).
            $this->reschedule->handle($appointment, $actor, $event->start);
            $summary['rescheduled']++;
        } else {
            // Già allineato (convergenza) o nulla da fare: no-op sull'appuntamento.
            $summary['skipped']++;
        }

        // In ogni caso aggiorna l'etag noto della mappatura, quando presente.
        $this->updateMappingEtag($mapping, $event->etag);
    }

    /**
     * Vero quando l'evento porta uno start valido, DIVERSO dallo scheduled_at corrente e
     * l'appuntamento è ancora attivo (riprogrammabile). È il predicato di convergenza: se lo
     * start combacia, non riprogrammiamo (niente ping-pong con il push).
     */
    private function needsReschedule(PulledEvent $event, Appointment $appointment): bool
    {
        return $event->start !== null
            && $this->isActive($appointment)
            && ! $event->start->equalTo($appointment->scheduled_at);
    }

    /**
     * Risolve l'appuntamento: prima per id ricavato dall'evento (extended property/UID),
     * poi in fallback per la mappatura (external id → appointment). Null se irrisolvibile.
     */
    private function resolveAppointment(PulledEvent $event, ?AppointmentExternalEvent $mapping): ?Appointment
    {
        if ($event->appointmentId !== null) {
            return Appointment::query()->find($event->appointmentId);
        }

        return $mapping?->appointment;
    }

    /**
     * Mappatura (connessione + external id) → riga {@see AppointmentExternalEvent}, con
     * l'appuntamento eager-loaded (serve org e stato).
     */
    private function mappingFor(CalendarConnection $connection, string $externalId): ?AppointmentExternalEvent
    {
        return AppointmentExternalEvent::query()
            ->where('calendar_connection_id', $connection->getKey())
            ->where('external_event_id', $externalId)
            ->with('appointment')
            ->first();
    }

    /**
     * Aggiorna l'etag della mappatura solo quando l'evento ne porta uno nuovo e diverso.
     */
    private function updateMappingEtag(?AppointmentExternalEvent $mapping, ?string $etag): void
    {
        if ($mapping === null || $etag === null || $etag === '' || $mapping->etag === $etag) {
            return;
        }

        $mapping->forceFill(['etag' => $etag])->save();
    }

    /**
     * Tenant guard: l'appuntamento deve appartenere all'ente della connessione. Chiude
     * qualsiasi fuga cross-tenant su id/mappature manipolabili dal calendario esterno.
     */
    private function belongsToConnectionOrg(?Appointment $appointment, CalendarConnection $connection): bool
    {
        return $appointment !== null && $appointment->organization_id === $connection->organization_id;
    }

    /**
     * Appuntamento "vivo" (occupa uno slot): Richiesto o Confermato. Gli stati terminali
     * non si riconciliano.
     */
    private function isActive(?Appointment $appointment): bool
    {
        return $appointment !== null && in_array(
            $appointment->status,
            [AppointmentStatus::Requested, AppointmentStatus::Confirmed],
            true,
        );
    }

    /**
     * Esegue una singola voce isolandone i guasti: qualunque eccezione (guardia di dominio
     * su slot/overlap/transizione, o altro) viene loggata e la voce saltata, SENZA abortire
     * la connessione. Il dettaglio completo resta server-side, sanificato.
     */
    private function guard(CalendarConnection $connection, callable $reconcileItem): void
    {
        try {
            $reconcileItem();
        } catch (Throwable $exception) {
            Log::warning('[calendar-reconcile] item skipped', [
                'connection_id' => $connection->getKey(),
                'error' => Str::of($exception->getMessage())->replaceMatches('/\s+/', ' ')->trim()->limit(300)->value(),
            ]);
        }
    }
}
