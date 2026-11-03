<?php

declare(strict_types=1);

namespace App\Support\Billing;

/**
 * Risultato minimale della creazione di una sessione di Checkout Stripe per una
 * donazione: l'id della sessione (salvato come provider_ref per la riconciliazione via
 * webhook) e l'URL ospitato su cui redirigere il donatore.
 *
 * DTO readonly: nessuna logica, solo trasporto tipizzato fra il seam
 * {@see CreatesDonationCheckout} e l'Action che lo consuma. Astrae la risposta Stripe
 * così il test può restituirne una versione deterministica senza rete.
 */
final readonly class DonationCheckoutSession
{
    public function __construct(
        public string $id,
        public string $url,
    ) {}
}
