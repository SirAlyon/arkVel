<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\AppointmentStatus;
use DomainException;

/**
 * Sollevata quando si tenta una transizione dell'appuntamento non ammessa dal
 * grafo definito in App\Enums\AppointmentStatus::allowedTransitions().
 *
 * È il wrapper di dominio che le Action in App\Actions\Appointments sollevano
 * quando la guardia canTransitionTo() fallisce: mantiene la superficie d'errore
 * coerente con InvalidFosterApplicationTransition (affido) e
 * InvalidAnimalStatusTransition (animali).
 */
final class InvalidAppointmentTransition extends DomainException
{
    public function __construct(
        public readonly AppointmentStatus $from,
        public readonly AppointmentStatus $to,
    ) {
        parent::__construct(
            __('Transizione dell\'appuntamento non consentita: da :from a :to.', [
                'from' => $from->getLabel(),
                'to' => $to->getLabel(),
            ]),
        );
    }

    public static function between(AppointmentStatus $from, AppointmentStatus $to): self
    {
        return new self($from, $to);
    }
}
