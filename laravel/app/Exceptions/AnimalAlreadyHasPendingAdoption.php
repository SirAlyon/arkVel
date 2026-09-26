<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Animal;
use DomainException;

/**
 * Sollevata (slice 7) quando una seconda candidatura per lo stesso animale
 * proverebbe a raggiungere AdoptionPending/Completed mentre un'altra
 * candidatura è già in adozione/completata: un animale può avere una sola
 * adozione attiva.
 *
 * Porta con sé l'animale in conflitto e l'id della candidatura confliggente.
 */
final class AnimalAlreadyHasPendingAdoption extends DomainException
{
    public function __construct(
        public readonly Animal $animal,
        public readonly int $conflictingApplicationId,
    ) {
        parent::__construct(
            __('L\'animale :name ha già un\'adozione in corso (candidatura #:id).', [
                'name' => $animal->name,
                'id' => $conflictingApplicationId,
            ]),
        );
    }

    public static function for(Animal $animal, int $conflictingApplicationId): self
    {
        return new self($animal, $conflictingApplicationId);
    }
}
