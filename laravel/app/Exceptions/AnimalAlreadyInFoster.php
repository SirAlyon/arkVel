<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Animal;
use DomainException;

/**
 * Sollevata (slice 3) quando si proverebbe ad aprire un secondo affido attivo
 * per un animale che ne ha già uno in corso: un animale può avere un solo
 * affido attivo alla volta.
 *
 * Porta con sé l'animale in conflitto e l'id dell'affido confliggente. Fa da
 * specchio a AnimalAlreadyHasPendingAdoption (adozione).
 */
final class AnimalAlreadyInFoster extends DomainException
{
    public function __construct(
        public readonly Animal $animal,
        public readonly int $conflictingPlacementId,
    ) {
        parent::__construct(
            __('L\'animale :name è già in affido (affido #:id).', [
                'name' => $animal->name,
                'id' => $conflictingPlacementId,
            ]),
        );
    }

    public static function for(Animal $animal, int $conflictingPlacementId): self
    {
        return new self($animal, $conflictingPlacementId);
    }
}
