<?php

declare(strict_types=1);

namespace App\Support\Calendar\Providers;

/**
 * Insieme delle modifiche estratte da un provider esterno durante un pull
 * incrementale (Cal-7). In Cal-4 esiste solo per definire il contratto del provider:
 * il Log provider restituisce sempre un insieme vuoto ({@see empty()}).
 *
 * La forma degli elementi di `$changed` è deliberatamente lasciata "opaca" (mixed):
 * il tipo concreto dell'evento remoto dipende dal provider (Google/CalDAV) e verrà
 * normalizzato dai rispettivi adattatori in Cal-5/6/7. Qui il contratto fissa solo
 * la struttura di trasporto.
 */
final readonly class PulledChanges
{
    /**
     * @param  list<mixed>  $changed  Eventi remoti creati/modificati dall'ultimo sync.
     * @param  list<string>  $deletedExternalIds  Id esterni degli eventi rimossi.
     * @param  string|null  $newSyncToken  Nuovo token incrementale (syncToken/ctag).
     */
    public function __construct(
        public array $changed = [],
        public array $deletedExternalIds = [],
        public ?string $newSyncToken = null,
    ) {}

    /**
     * Insieme vuoto: nessuna modifica, nessuna cancellazione, token invariato. Usato
     * dal Log provider e come default sicuro quando non c'è nulla da riconciliare.
     */
    public static function empty(): self
    {
        return new self;
    }
}
