<?php

declare(strict_types=1);

namespace App\Support\Billing;

use App\Actions\Donations\StartRecurringDonationCheckout;
use App\Models\RecurringDonation;

/**
 * Seam (porta) per la gestione di una donazione RICORRENTE lato Stripe (P4-7): apertura
 * del Checkout in modalità subscription e disdetta dell'abbonamento.
 *
 * Dependency Inversion, stesso pattern di {@see CreatesDonationCheckout}: l'Action
 * {@see StartRecurringDonationCheckout} (e la CancelRecurringDonation) dipendono da QUESTA
 * interfaccia, non dallo SDK Stripe. In produzione il binding è
 * {@see StripeRecurringDonationCheckout} (chiama Stripe); nei test si lega un fake
 * deterministico che NON tocca la rete.
 */
interface ManagesRecurringDonation
{
    /**
     * Crea la sessione di Checkout `mode=subscription` per l'abbonamento e restituisce
     * id + URL ospitato su cui redirigere il donatore. Il DTO è condiviso con il flusso
     * una tantum ({@see DonationCheckoutSession}).
     *
     * @param  string  $successUrl  URL assoluto di ritorno dopo l'attivazione
     * @param  string  $cancelUrl  URL assoluto di ritorno in caso di annullamento
     */
    public function createCheckout(RecurringDonation $recurring, string $successUrl, string $cancelUrl): DonationCheckoutSession;

    /**
     * Disdice immediatamente l'abbonamento Stripe indicato. È una donazione: la disdetta
     * immediata (non a fine ciclo) è il comportamento desiderato.
     */
    public function cancel(string $stripeSubscriptionId): void;
}
