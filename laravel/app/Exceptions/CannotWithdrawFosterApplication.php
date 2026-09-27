<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Models\FosterApplication;
use App\Models\User;
use DomainException;

/**
 * Sollevata (slice 3) quando un utente prova a ritirare una richiesta di affido
 * che non gli appartiene: il ritiro è un'azione esclusiva dell'AFFIDATARIO
 * proprietario.
 *
 * È una guardia di DOMINIO (non solo di autorizzazione HTTP): l'action
 * WithdrawFosterApplication è l'unico punto in cui una richiesta viene ritirata
 * e deve rifiutare esplicitamente un attore diverso dal proprietario, a
 * prescindere dalla Policy che protegge l'entry-point UI. Fa da specchio a
 * CannotWithdrawApplication (adozione).
 */
final class CannotWithdrawFosterApplication extends DomainException
{
    public function __construct(
        public readonly FosterApplication $application,
        public readonly User $actor,
    ) {
        parent::__construct(
            __('Solo l\'affidatario che ha inviato la richiesta può ritirarla.'),
        );
    }

    public static function notOwner(FosterApplication $application, User $actor): self
    {
        return new self($application, $actor);
    }
}
