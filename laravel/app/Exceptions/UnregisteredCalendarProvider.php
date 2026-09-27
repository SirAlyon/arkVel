<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\CalendarProvider;
use App\Support\Calendar\CalendarProviderRegistry;
use RuntimeException;

/**
 * Sollevata quando si richiede al {@see CalendarProviderRegistry}
 * un provider di calendario non registrato (es. Google/CalDAV prima che Cal-5/6 ne
 * abbiano fatto il binding). È un errore di configurazione del programmatore, non un
 * errore di dominio: RuntimeException con messaggio esplicito.
 */
final class UnregisteredCalendarProvider extends RuntimeException
{
    public static function for(CalendarProvider $provider): self
    {
        return new self(sprintf(
            'Nessun provider di calendario registrato per "%s". Registra un ExternalCalendarProvider nel CalendarSyncServiceProvider.',
            $provider->value,
        ));
    }
}
