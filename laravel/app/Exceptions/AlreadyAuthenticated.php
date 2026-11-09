<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Support\Auth\GuestOnly;
use DomainException;

/**
 * Sollevata dalle Action dei moduli per ospiti (accesso, registrazione, password) quando
 * la sessione è già autenticata ({@see GuestOnly}). Il middleware `guest` protegge solo la
 * GET della pagina: le richieste Livewire successive (/livewire/update) non lo ripassano,
 * quindi è l'Action a fermarsi. Porta con sé la destinazione dell'utente: le pagine di
 * autenticazione la usano per il redirect.
 */
final class AlreadyAuthenticated extends DomainException
{
    public function __construct(public readonly string $destination)
    {
        parent::__construct('The user is already authenticated.');
    }

    public static function redirectTo(string $destination): self
    {
        return new self($destination);
    }
}
