<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Actions\Donations\StartDonationCheckout;
use RuntimeException;

/**
 * Sollevata quando l'importo di una donazione è fuori dai limiti consentiti (in
 * centesimi). Guardia di dominio valutata PRIMA di scrivere in
 * {@see StartDonationCheckout}, così un importo illegittimo non lascia righe orfane né
 * tocca Stripe.
 *
 * Limiti: minimo {@see StartDonationCheckout::MIN_AMOUNT} (1€) per coprire le soglie
 * minime di Stripe; massimo {@see StartDonationCheckout::MAX_AMOUNT} (10.000€) come
 * anti-abuso ragionevole per una donazione una tantum da form pubblico.
 */
final class InvalidDonationAmount extends RuntimeException
{
    public static function outOfRange(int $amount, int $min, int $max): self
    {
        return new self(sprintf(
            'Importo della donazione non valido: %d centesimi (ammessi da %d a %d).',
            $amount,
            $min,
            $max,
        ));
    }
}
