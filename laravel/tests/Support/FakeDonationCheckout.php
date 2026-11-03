<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Donation;
use App\Support\Billing\CreatesDonationCheckout;
use App\Support\Billing\DonationCheckoutSession;

/**
 * Fake deterministico del seam di Checkout ({@see CreatesDonationCheckout}) per i test:
 * NON tocca Stripe né la rete. Restituisce un id/URL prevedibili e registra l'ultima
 * donazione e gli URL ricevuti, così i test possono asserire la delega e l'uso del
 * provider_ref senza chiamate reali.
 *
 * Si lega nel container al posto dell'impl reale (StripeDonationCheckout) via
 * app()->instance(CreatesDonationCheckout::class, new FakeDonationCheckout()).
 */
final class FakeDonationCheckout implements CreatesDonationCheckout
{
    public string $id = 'cs_test_fake_session';

    public string $url = 'https://checkout.stripe.test/c/pay/cs_test_fake_session';

    public ?Donation $lastDonation = null;

    public ?string $lastSuccessUrl = null;

    public ?string $lastCancelUrl = null;

    public function create(Donation $donation, string $successUrl, string $cancelUrl): DonationCheckoutSession
    {
        $this->lastDonation = $donation;
        $this->lastSuccessUrl = $successUrl;
        $this->lastCancelUrl = $cancelUrl;

        return new DonationCheckoutSession($this->id, $this->url);
    }
}
