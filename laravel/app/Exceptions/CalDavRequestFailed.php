<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\Calendar\CalDav\CalDavClient;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Sollevata quando una richiesta CalDAV (PUT/DELETE/REPORT/PROPFIND) fallisce in modo
 * non recuperabile. È un errore di dominio d'integrazione, non di configurazione: il
 * server ha risposto con uno status inatteso (né 2xx né una condizione tollerata come
 * 404/410/412).
 *
 * SICUREZZA DEI MESSAGGI: il messaggio contiene SOLO metodo + status + uno snippet
 * SANIFICATO del corpo. NON contiene mai l'header Authorization né l'app-password:
 * quelli vivono solo nella richiesta HTTP, mai negli errori. Così il messaggio può
 * finire in `last_error` (visibile nel pannello ente) senza leak di credenziali.
 *
 * Prodotta da {@see CalDavClient}: mantiene le eccezioni del sync CalDAV in un unico
 * tipo, distinguibile da {@see CalendarProviderNotConfigured} (che qui NON esiste: il
 * CalDAV è per-connessione, ogni ente inserisce il proprio server, nessun config-gating).
 */
final class CalDavRequestFailed extends RuntimeException
{
    /**
     * Costruisce l'eccezione dalla risposta HTTP fallita, sanificando metodo, status e
     * un breve snippet del corpo (mai i segreti della richiesta).
     */
    public static function from(string $method, Response $response): self
    {
        return new self(sprintf(
            'CalDAV %s failed (HTTP %d): %s',
            strtoupper($method),
            $response->status(),
            Str::of($response->body())->replaceMatches('/\s+/', ' ')->trim()->limit(200)->value(),
        ));
    }
}
