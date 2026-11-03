<?php

declare(strict_types=1);

namespace App\Support\Calendar\Providers;

use App\Enums\CalendarProvider;
use App\Jobs\SyncAppointmentToCalendarsJob;
use App\Models\CalendarConnection;
use App\Support\Calendar\AppointmentEventMapper;
use App\Support\Calendar\CalendarEventData;
use App\Support\Calendar\CalendarProviderRegistry;

/**
 * Contratto di un provider di calendario esterno (Google/CalDAV/Log).
 *
 * È il punto di ESTENSIONE della sincronizzazione (Open/Closed): aggiungere Google o
 * CalDAV significa fornire una nuova implementazione di questa interfaccia e
 * registrarla nel {@see CalendarProviderRegistry} — senza
 * toccare la pipeline di push ({@see SyncAppointmentToCalendarsJob}), che
 * dipende solo da questo contratto (Dependency Inversion).
 *
 * Tutte le operazioni ricevono la {@see CalendarConnection} (per credenziali/calendario
 * di destinazione) e consumano il {@see CalendarEventData} già minimizzato: il provider
 * NON accede al model Appointment, così "cosa lascia ArkVel" resta deciso in un unico
 * punto ({@see AppointmentEventMapper}).
 *
 * Contratto piccolo e mirato (Interface Segregation): push, delete e pull, nient'altro.
 */
interface ExternalCalendarProvider
{
    /**
     * Provider gestito da questa implementazione (chiave nel registry).
     */
    public function provider(): CalendarProvider;

    /**
     * Crea o aggiorna (create-or-update) l'evento sul calendario esterno.
     *
     * Se $existingExternalId è null l'evento va creato; altrimenti va aggiornato
     * (usando $existingEtag per l'update condizionale, dove supportato). Ritorna
     * l'id (ed eventuale etag) risultante, che la pipeline persiste per i push futuri.
     */
    public function pushEvent(
        CalendarConnection $connection,
        CalendarEventData $event,
        ?string $existingExternalId,
        ?string $existingEtag,
    ): PushedEvent;

    /**
     * Cancella l'evento dal calendario esterno. $etag consente la cancellazione
     * condizionale dove supportata. Idempotente lato pipeline: viene chiamata solo se
     * esiste una mappatura per l'evento.
     */
    public function deleteEvent(
        CalendarConnection $connection,
        string $externalId,
        ?string $etag,
    ): void;

    /**
     * Estrae le modifiche incrementali dal provider (Cal-7). In Cal-4 la pipeline non
     * la usa; il Log provider restituisce {@see PulledChanges::empty()}.
     */
    public function pullChanges(CalendarConnection $connection): PulledChanges;
}
