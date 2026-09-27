<?php

declare(strict_types=1);

namespace App\Support\Calendar;

use App\Enums\CalendarProvider;
use App\Exceptions\UnregisteredCalendarProvider;
use App\Models\CalendarConnection;
use App\Providers\CalendarSyncServiceProvider;
use App\Support\Calendar\Providers\ExternalCalendarProvider;

/**
 * Registro dei provider di calendario esterno: mappa un {@see CalendarProvider} (o la
 * {@see CalendarConnection} che lo dichiara) all'implementazione
 * {@see ExternalCalendarProvider} corrispondente.
 *
 * È il punto di INDIREZIONE fra la pipeline di push e i provider concreti (Dependency
 * Inversion): il job non conosce Google/CalDAV/Log, chiede al registry. Le
 * implementazioni si aggiungono col binding nel {@see CalendarSyncServiceProvider}
 * (Open/Closed) — in Cal-4 è registrato solo il Log provider; Google/CalDAV in Cal-5/6.
 *
 * Un provider non registrato solleva {@see UnregisteredCalendarProvider}: errore di
 * configurazione esplicito, non un fallimento silenzioso.
 */
final class CalendarProviderRegistry
{
    /**
     * @var array<value-of<CalendarProvider>, ExternalCalendarProvider>
     */
    private array $providers = [];

    /**
     * Registra (o sostituisce) l'implementazione per il suo provider dichiarato.
     * La chiave è {@see ExternalCalendarProvider::provider()}: nessun rischio di
     * disallineamento fra chiave ed enum gestito.
     */
    public function register(ExternalCalendarProvider $provider): void
    {
        $this->providers[$provider->provider()->value] = $provider;
    }

    /**
     * Risolve l'implementazione per l'enum dato.
     *
     * @throws UnregisteredCalendarProvider se non c'è un provider registrato.
     */
    public function for(CalendarProvider $provider): ExternalCalendarProvider
    {
        return $this->providers[$provider->value]
            ?? throw UnregisteredCalendarProvider::for($provider);
    }

    /**
     * Risolve l'implementazione per la connessione data (comodità: legge il provider
     * dalla connessione e delega a {@see for()}).
     *
     * @throws UnregisteredCalendarProvider se non c'è un provider registrato.
     */
    public function forConnection(CalendarConnection $connection): ExternalCalendarProvider
    {
        return $this->for($connection->provider);
    }

    /**
     * Indica se esiste un provider registrato per l'enum dato (utile alla UI per
     * abilitare/disabilitare i flussi di connessione reali non ancora implementati).
     */
    public function has(CalendarProvider $provider): bool
    {
        return isset($this->providers[$provider->value]);
    }
}
