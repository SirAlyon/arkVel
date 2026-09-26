<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\Animal;
use App\Models\User;
use DomainException;

/**
 * Sollevata (slice 7) quando un utente prova a candidarsi per un animale per
 * cui ha già una candidatura: la coppia (animal_id, user_id) è unica anche a
 * livello DB.
 */
final class DuplicateApplication extends DomainException
{
    public function __construct(
        public readonly Animal $animal,
        public readonly User $adopter,
    ) {
        parent::__construct(
            __('Esiste già una candidatura di :adopter per :animal.', [
                'adopter' => $adopter->name,
                'animal' => $animal->name,
            ]),
        );
    }

    public static function for(Animal $animal, User $adopter): self
    {
        return new self($animal, $adopter);
    }
}
