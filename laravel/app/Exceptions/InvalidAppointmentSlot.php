<?php

declare(strict_types=1);

namespace App\Exceptions;

use Carbon\CarbonInterface;
use DomainException;

/**
 * Sollevata quando l'orario richiesto per un appuntamento non è valido: al momento
 * l'unico caso è uno slot NON nel futuro (nel passato o adesso).
 *
 * È una guardia di dominio di RequestAppointment, valutata PRIMA di scrivere: uno
 * slot invalido non deve mai lasciare righe orfane.
 */
final class InvalidAppointmentSlot extends DomainException
{
    public function __construct(
        public readonly CarbonInterface $scheduledAt,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function inThePast(CarbonInterface $scheduledAt): self
    {
        return new self(
            $scheduledAt,
            __('L\'appuntamento deve essere programmato in un momento futuro (:when non è valido).', [
                'when' => $scheduledAt->toDateTimeString(),
            ]),
        );
    }
}
