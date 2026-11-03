<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\SyncAppointmentToCalendarsJob;
use App\Models\Appointment;
use App\Models\AppointmentExternalEvent;
use App\Models\CalendarConnection;

/**
 * Aggancia la pipeline di sync a TUTTE le mutazioni di un appuntamento, in modo
 * trasparente: qualunque percorso (Action di dominio, reschedule da calendario,
 * creazione da slot, ecc.) fa scattare il push senza dover toccare ogni singola
 * Action. Registrato via #[ObservedBy] sul model {@see Appointment}.
 *
 * ECONOMICITÀ: la guardia {@see hasActiveConnections()} fa un solo `exists()` prima
 * di accodare. Nella stragrande maggioranza dei casi (nessuna connessione configurata:
 * suite di test, seeder, enti senza sync) NON si accoda nulla e il costo è una query
 * booleana. Il job vero e proprio (e la risoluzione dei provider) paga solo chi ha
 * davvero una connessione attiva.
 *
 * afterCommit: il dispatch avviene DOPO il commit della transazione, così il job non
 * gira su uno stato non ancora persistito (o su una transazione poi annullata).
 */
class AppointmentObserver
{
    /**
     * Ogni salvataggio (create o update) sincronizza l'appuntamento in upsert.
     */
    public function saved(Appointment $appointment): void
    {
        if (! $this->hasActiveConnections($appointment)) {
            return;
        }

        SyncAppointmentToCalendarsJob::dispatch(
            $appointment->getKey(),
            SyncAppointmentToCalendarsJob::OP_UPSERT,
        )->afterCommit();
    }

    /**
     * La cancellazione dell'appuntamento rimuove l'evento dai calendari esterni.
     *
     * Usiamo `deleting` (PRIMA della cancellazione) per FOTOGRAFARE le mappature: la FK
     * cascade su appointment_external_events le rimuove insieme all'appuntamento, quindi
     * dopo non sarebbero più leggibili. I riferimenti remoti (connessione + external id +
     * etag) viaggiano nel payload del job, che cancella gli eventi esterni senza più
     * dipendere da appuntamento o mappatura. Il dispatch resta afterCommit: se la
     * cancellazione viene annullata (rollback), nessun delete remoto parte.
     */
    public function deleting(Appointment $appointment): void
    {
        $deletions = $appointment->externalEvents()
            ->get()
            ->map(fn (AppointmentExternalEvent $mapping): array => [
                'connection_id' => $mapping->calendar_connection_id,
                'external_id' => $mapping->external_event_id,
                'etag' => $mapping->etag,
            ])
            ->all();

        // Niente da cancellare da nessuna parte se l'appuntamento non era mai stato
        // spinto su alcun calendario esterno.
        if ($deletions === []) {
            return;
        }

        SyncAppointmentToCalendarsJob::dispatch(
            $appointment->getKey(),
            SyncAppointmentToCalendarsJob::OP_DELETE,
            $deletions,
        )->afterCommit();
    }

    /**
     * Guardia economica: l'ente ha almeno una connessione attiva? Un solo exists()
     * per organization_id (senza caricare il model Organization), così quando non c'è
     * sync configurato la suite/seeder non pagano nulla oltre a una query booleana.
     */
    private function hasActiveConnections(Appointment $appointment): bool
    {
        return CalendarConnection::query()
            ->where('organization_id', $appointment->organization_id)
            ->active()
            ->exists();
    }
}
