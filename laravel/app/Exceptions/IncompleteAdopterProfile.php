<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\User;
use DomainException;

/**
 * Sollevata (slice 7) quando un utente invia una candidatura senza un profilo
 * adottante completo o senza aver prestato i consensi richiesti.
 */
final class IncompleteAdopterProfile extends DomainException
{
    public function __construct(
        public readonly User $adopter,
    ) {
        parent::__construct(
            __('Profilo adottante incompleto: completa il questionario e i consensi prima di candidarti.'),
        );
    }

    public static function for(User $adopter): self
    {
        return new self($adopter);
    }
}
