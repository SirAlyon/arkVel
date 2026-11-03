<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Actions\Donations\StartDonationCheckout;
use RuntimeException;

/**
 * Sollevata quando una donazione da ospite (nessun utente autenticato) non fornisce
 * un'email del donatore. Serve almeno un'identità: un utente loggato OPPURE un'email
 * d'ospite — per la ricevuta Stripe e la tracciabilità contabile della donazione.
 *
 * Guardia di dominio valutata PRIMA di scrivere in {@see StartDonationCheckout}.
 */
final class MissingDonorIdentity extends RuntimeException
{
    public static function make(): self
    {
        return new self(
            'Donazione da ospite senza identità: è richiesta un\'email del donatore quando non c\'è un utente autenticato.',
        );
    }
}
