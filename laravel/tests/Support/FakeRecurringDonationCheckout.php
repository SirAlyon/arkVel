<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\RecurringDonation;
use App\Support\Billing\DonationCheckoutSession;
use App\Support\Billing\ManagesRecurringDonation;

/**
 * Fake deterministico del seam ricorrente ({@see ManagesRecurringDonation}) per i test:
 * NON tocca Stripe né la rete. Restituisce un id/URL prevedibili, registra l'ultima
 * ricorrente e gli URL ricevuti all'avvio, e l'id dell'abbonamento disdetto, così i test
 * possono asserire la delega senza chiamate reali.
 *
 * Si lega nel container al posto dell'impl reale (StripeRecurringDonationCheckout) via
 * app()->instance(ManagesRecurringDonation::class, new FakeRecurringDonationCheckout()).
 */
final class FakeRecurringDonationCheckout implements ManagesRecurringDonation
{
    public string $id = 'cs_test_fake_subscription';

    public string $url = 'https://checkout.stripe.test/c/pay/cs_test_fake_subscription';

    public ?RecurringDonation $lastRecurring = null;

    public ?string $lastSuccessUrl = null;

    public ?string $lastCancelUrl = null;

    public ?string $canceledSubscriptionId = null;

    public int $cancelCalls = 0;

    public function createCheckout(RecurringDonation $recurring, string $successUrl, string $cancelUrl): DonationCheckoutSession
    {
        $this->lastRecurring = $recurring;
        $this->lastSuccessUrl = $successUrl;
        $this->lastCancelUrl = $cancelUrl;

        return new DonationCheckoutSession($this->id, $this->url);
    }

    public function cancel(string $stripeSubscriptionId): void
    {
        $this->cancelCalls++;
        $this->canceledSubscriptionId = $stripeSubscriptionId;
    }
}
