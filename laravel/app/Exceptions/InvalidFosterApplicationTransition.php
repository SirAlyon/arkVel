<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\FosterApplicationStatus;
use DomainException;

/**
 * Sollevata quando si tenta una transizione della richiesta di affido non
 * ammessa dal grafo definito in
 * App\States\Foster\FosterApplicationState::config().
 *
 * È il wrapper di dominio che le Action (slice 3) sollevano al posto di far
 * trapelare Spatie\ModelStates\Exceptions\CouldNotPerformTransition: mantiene
 * la superficie d'errore coerente con InvalidApplicationTransition (adozione).
 */
final class InvalidFosterApplicationTransition extends DomainException
{
    public function __construct(
        public readonly FosterApplicationStatus $from,
        public readonly FosterApplicationStatus $to,
    ) {
        parent::__construct(
            __('Transizione della richiesta di affido non consentita: da :from a :to.', [
                'from' => $from->getLabel(),
                'to' => $to->getLabel(),
            ]),
        );
    }

    public static function between(FosterApplicationStatus $from, FosterApplicationStatus $to): self
    {
        return new self($from, $to);
    }
}
