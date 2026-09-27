<?php

declare(strict_types=1);

namespace App\Support\Calendar\Providers;

use App\Models\AppointmentExternalEvent;
use App\Support\Calendar\CalendarReconciler;
use Illuminate\Support\Carbon;

/**
 * Forma NORMALIZZATA di un evento creato/modificato letto da un calendario esterno
 * durante il pull di riconciliazione (Cal-7). È il "linguaggio comune" fra i provider
 * (Google/CalDAV, dialetti diversi) e il {@see CalendarReconciler}:
 * ogni provider traduce il proprio formato grezzo in questo VO, così la riconciliazione
 * NON conosce le peculiarità del provider (Dependency Inversion).
 *
 * Value object immutabile (readonly). Campi:
 *  - $externalId: id/href dell'evento remoto (chiave della mappatura
 *    {@see AppointmentExternalEvent::$external_event_id});
 *  - $appointmentId: id dell'appuntamento ArkVel ricavato dall'evento (extended property
 *    Google o UID `appointment-{id}@…` CalDAV). Null quando non ricavabile: la
 *    riconciliazione ricadrà sulla mappatura per external id;
 *  - $start: istante d'inizio in UTC ({@see Carbon}). Null quando il provider non ha
 *    potuto leggerlo (CalDAV senza multiget): solo cancellazioni/uid riconciliano;
 *  - $status: stato NEUTRO dell'evento fra 'confirmed'|'tentative'|'cancelled' (i valori
 *    del dialetto Google, riusati come lingua comune);
 *  - $etag: etag/versione remota corrente, da persistere sulla mappatura dopo il sync.
 */
final readonly class PulledEvent
{
    /**
     * @param  string  $status  Uno di 'confirmed'|'tentative'|'cancelled'.
     */
    public function __construct(
        public string $externalId,
        public ?int $appointmentId = null,
        public ?Carbon $start = null,
        public string $status = 'confirmed',
        public ?string $etag = null,
    ) {}

    /**
     * Vero quando l'evento remoto è stato annullato (status neutro 'cancelled'): la
     * riconciliazione annullerà l'appuntamento ArkVel corrispondente se ancora attivo.
     */
    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }
}
