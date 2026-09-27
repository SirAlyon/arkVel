<?php

declare(strict_types=1);

namespace App\Support\Calendar\Providers;

use App\Models\AppointmentExternalEvent;

/**
 * Esito di un push (create-or-update) di un evento verso un provider esterno.
 *
 * Contiene il minimo indispensabile per rendere IDEMPOTENTE il push successivo:
 * l'id dell'evento lato provider e l'eventuale ETag/versione per l'aggiornamento
 * condizionale. La pipeline persiste questi valori nella mappatura
 * {@see AppointmentExternalEvent}.
 */
final readonly class PushedEvent
{
    public function __construct(
        public string $externalId,
        public ?string $etag = null,
    ) {}
}
