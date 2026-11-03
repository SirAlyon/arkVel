<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\ApplicationStatus;
use DomainException;

/**
 * Sollevata quando si tenta una transizione della candidatura non ammessa dal
 * grafo definito in App\States\Application\ApplicationState::config().
 *
 * È il wrapper di dominio che le Action (slice 7) sollevano al posto di far
 * trapelare Spatie\ModelStates\Exceptions\CouldNotPerformTransition: mantiene
 * la superficie d'errore coerente con InvalidAnimalStatusTransition.
 */
final class InvalidApplicationTransition extends DomainException
{
    public function __construct(
        public readonly ApplicationStatus $from,
        public readonly ApplicationStatus $to,
    ) {
        parent::__construct(
            __('Transizione della candidatura non consentita: da :from a :to.', [
                'from' => $from->getLabel(),
                'to' => $to->getLabel(),
            ]),
        );
    }

    public static function between(ApplicationStatus $from, ApplicationStatus $to): self
    {
        return new self($from, $to);
    }
}
