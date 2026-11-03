<?php

declare(strict_types=1);

namespace App\Support\Calendar\Providers;

use App\Support\Calendar\CalendarReconciler;

/**
 * Insieme delle modifiche estratte da un provider esterno durante un pull
 * incrementale (Cal-7). Il Log provider restituisce sempre un insieme vuoto
 * ({@see empty()}).
 *
 * Gli elementi di `$changed` sono {@see PulledEvent} NORMALIZZATI: ogni provider
 * (Google/CalDAV, dialetti diversi) traduce il proprio formato grezzo in questo VO
 * comune, così il {@see CalendarReconciler} riconcilia senza
 * conoscere le peculiarità del provider. `$deletedExternalIds` porta gli id/href degli
 * eventi RIMOSSI a monte (equivalenti a un annullamento).
 */
final readonly class PulledChanges
{
    /**
     * @param  list<PulledEvent>  $changed  Eventi remoti creati/modificati dall'ultimo sync.
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
