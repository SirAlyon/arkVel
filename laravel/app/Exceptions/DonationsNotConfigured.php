<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Actions\Donations\StartDonationCheckout;
use App\Support\Billing\StripeConfig;
use RuntimeException;

/**
 * Sollevata quando si tenta un flusso di donazione mentre Stripe NON è configurato
 * (publishable/secret key assenti). È un errore di CONFIGURAZIONE dell'installazione, non
 * di dominio: il flusso resta inerte finché un operatore non imposta le chiavi (da UI
 * admin in P4-8 o da .env).
 *
 * Config-gating (P4-6): l'Action {@see StartDonationCheckout} la
 * solleva quando {@see StripeConfig::configured()} è falso, e la UI pubblica nasconde la
 * sezione di donazione con un avviso localizzato. Nessun segreto nel messaggio.
 */
final class DonationsNotConfigured extends RuntimeException
{
    public static function make(): self
    {
        return new self(
            'Le donazioni non sono configurate: imposta le chiavi Stripe (publishable e secret) per abilitare il flusso di donazione.',
        );
    }
}
