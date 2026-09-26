<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\AnimalStatus;
use DomainException;

/**
 * Sollevata quando si tenta una transizione di stato animale non ammessa dal
 * grafo definito su App\Enums\AnimalStatus::allowedTransitions().
 */
final class InvalidAnimalStatusTransition extends DomainException
{
    public function __construct(
        public readonly AnimalStatus $from,
        public readonly AnimalStatus $to,
    ) {
        parent::__construct(
            __('Transizione di stato non consentita: da :from a :to.', [
                'from' => $from->getLabel(),
                'to' => $to->getLabel(),
            ]),
        );
    }

    public static function between(AnimalStatus $from, AnimalStatus $to): self
    {
        return new self($from, $to);
    }
}
