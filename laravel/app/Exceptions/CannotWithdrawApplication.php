<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\AdoptionApplication;
use App\Models\User;
use DomainException;

/**
 * Sollevata (slice 7) quando un utente prova a ritirare una candidatura che non
 * gli appartiene: il ritiro è un'azione esclusiva dell'ADOTTANTE proprietario.
 *
 * È una guardia di DOMINIO (non solo di autorizzazione HTTP): l'action
 * WithdrawApplication è l'unico punto in cui una candidatura viene ritirata e
 * deve rifiutare esplicitamente un attore diverso dal proprietario, a
 * prescindere dalla Policy che protegge l'entry-point UI.
 */
final class CannotWithdrawApplication extends DomainException
{
    public function __construct(
        public readonly AdoptionApplication $application,
        public readonly User $actor,
    ) {
        parent::__construct(
            __('Solo l\'adottante che ha inviato la candidatura può ritirarla.'),
        );
    }

    public static function notOwner(AdoptionApplication $application, User $actor): self
    {
        return new self($application, $actor);
    }
}
