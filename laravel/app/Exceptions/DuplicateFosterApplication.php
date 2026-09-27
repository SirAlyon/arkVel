<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Animal;
use App\Models\User;
use DomainException;

/**
 * Sollevata (slice 3) quando un utente prova a chiedere l'affido di un animale
 * per cui ha già una richiesta: la coppia (animal_id, user_id) è unica anche a
 * livello DB.
 *
 * Fa da specchio a DuplicateApplication (flusso adozione).
 */
final class DuplicateFosterApplication extends DomainException
{
    public function __construct(
        public readonly Animal $animal,
        public readonly User $carer,
    ) {
        parent::__construct(
            __('Esiste già una richiesta di affido di :carer per :animal.', [
                'carer' => $carer->name,
                'animal' => $animal->name,
            ]),
        );
    }

    public static function for(Animal $animal, User $carer): self
    {
        return new self($animal, $carer);
    }
}
