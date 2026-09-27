<?php

declare(strict_types=1);

namespace App\Support\Calendar\Providers;

use App\Enums\CalendarProvider;
use App\Models\CalendarConnection;
use App\Support\Calendar\CalendarEventData;
use Illuminate\Support\Facades\Log;

/**
 * Provider FITTIZIO che non parla con alcun servizio esterno: logga soltanto e
 * restituisce esiti deterministici. Rende l'intera pipeline di sync dimostrabile e
 * testabile END-TO-END senza credenziali reali (demo locale + suite di test), prima
 * che arrivino Google (Cal-5) e CalDAV (Cal-6).
 *
 * NESSUN I/O esterno: solo Log::info. Gli id/etag prodotti sono deterministici
 * rispetto all'appuntamento, così i test possono asserirli e la seconda push dello
 * stesso appuntamento aggiorna la stessa mappatura invece di duplicarla.
 *
 * È l'implementazione di riferimento del contratto {@see ExternalCalendarProvider}:
 * i provider reali seguiranno la stessa forma (create-or-update idempotente).
 */
final class LogCalendarProvider implements ExternalCalendarProvider
{
    public function provider(): CalendarProvider
    {
        return CalendarProvider::Log;
    }

    public function pushEvent(
        CalendarConnection $connection,
        CalendarEventData $event,
        ?string $existingExternalId,
        ?string $existingEtag,
    ): PushedEvent {
        // Id deterministico per appuntamento: alla seconda push resta lo stesso, così
        // la pipeline riconosce l'evento e lo aggiorna (nessun doppione). L'etag cambia
        // a ogni push (deriva dalla SEQUENCE monotòna dell'evento) per simulare il
        // versioning remoto.
        $externalId = $existingExternalId ?? 'log-'.$event->appointmentId;
        $etag = 'log-etag-'.$event->sequence;

        Log::info('[calendar-sync] LogCalendarProvider push', [
            'connection_id' => $connection->getKey(),
            'operation' => $existingExternalId === null ? 'create' : 'update',
            'external_id' => $externalId,
            'uid' => $event->uid,
            'summary' => $event->summary,
            'status' => $event->status,
        ]);

        return new PushedEvent($externalId, $etag);
    }

    public function deleteEvent(
        CalendarConnection $connection,
        string $externalId,
        ?string $etag,
    ): void {
        Log::info('[calendar-sync] LogCalendarProvider delete', [
            'connection_id' => $connection->getKey(),
            'external_id' => $externalId,
        ]);
    }

    public function pullChanges(CalendarConnection $connection): PulledChanges
    {
        // Il provider demo non ha una sorgente esterna: nessuna modifica da importare.
        return PulledChanges::empty();
    }
}
