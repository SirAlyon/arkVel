<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\Calendar\Google\GoogleOAuth;
use RuntimeException;

/**
 * Sollevata quando si tenta un'operazione di rete verso un provider di calendario che
 * NON è configurato (credenziali OAuth assenti). È un errore di CONFIGURAZIONE
 * dell'installazione, non di dominio: la funzione resta inerte finché l'operatore non
 * imposta le variabili d'ambiente.
 *
 * Config-gating (Cal-5): i metodi di rete del {@see GoogleOAuth} e del
 * GoogleCalendarProvider la sollevano quando client_id/client_secret sono vuoti, e la
 * UI disabilita l'azione di connessione con un avviso localizzato. Nessun segreto nel
 * messaggio: indica solo QUALE provider va configurato.
 */
final class CalendarProviderNotConfigured extends RuntimeException
{
    public static function google(): self
    {
        return new self(
            'Google Calendar non è configurato: imposta GOOGLE_CALENDAR_CLIENT_ID e GOOGLE_CALENDAR_CLIENT_SECRET per abilitare la sincronizzazione.',
        );
    }
}
