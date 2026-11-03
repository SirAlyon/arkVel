<?php

declare(strict_types=1);

namespace App\Support\Billing;

use App\Actions\Donations\StartDonationCheckout;
use App\Models\Donation;

/**
 * Seam (porta) per la creazione di una sessione di Checkout Stripe a partire da una
 * donazione già persistita in stato Pending.
 *
 * Dependency Inversion: l'Action {@see StartDonationCheckout}
 * dipende da QUESTA interfaccia, non dallo SDK Stripe. In produzione il binding è
 * {@see StripeDonationCheckout} (chiama Stripe); nei test si lega un fake deterministico
 * che NON tocca la rete. È lo stesso pattern dei provider di calendario (porta +
 * implementazioni intercambiabili, binding nel Service Provider).
 */
interface CreatesDonationCheckout
{
    /**
     * Crea la sessione di Checkout per la donazione e restituisce id + URL ospitato.
     *
     * @param  string  $successUrl  URL assoluto di ritorno dopo il pagamento riuscito
     * @param  string  $cancelUrl  URL assoluto di ritorno in caso di annullamento
     */
    public function create(Donation $donation, string $successUrl, string $cancelUrl): DonationCheckoutSession;
}
