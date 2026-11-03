<?php

declare(strict_types=1);

namespace App\Support\Billing;

use App\Exceptions\DonationsNotConfigured;
use App\Models\Donation;
use App\Models\RecurringDonation;
use Stripe\Checkout\Session;
use Stripe\StripeClient;

/**
 * Implementazione reale del seam {@see ManagesRecurringDonation}: crea una sessione di
 * Checkout Stripe in modalità `subscription` (abbonamento mensile) e disdice
 * l'abbonamento, usando lo SDK Stripe incluso in Cashier.
 *
 * SCELTA DI DESIGN (documentata): usiamo Stripe Checkout `mode=subscription` con un
 * `price_data` ricorrente INLINE (niente catalogo prodotti/prezzi su Stripe), non gli
 * helper di Cashier (`newSubscription(...)->checkout()`). Motivo: lo stato di dominio
 * vive nel model {@see RecurringDonation} e nella riga {@see Donation}; la
 * sincronizzazione avviene esclusivamente via webhook. Non usiamo le tabelle `subscriptions`
 * di Cashier come fonte di verità: Checkout diretto mantiene il flusso identico e
 * simmetrico alla donazione una tantum ({@see StripeDonationCheckout}), con un solo seam
 * fakeable e nessuna dipendenza dal customer Cashier.
 *
 * Costruisce il client dalla secret key risolta da {@see StripeConfig} (DB-over-env). Se
 * la secret key è assente solleva {@see DonationsNotConfigured} (backstop: l'Action
 * verifica già `configured()` a monte).
 *
 * Il `metadata.recurring_donation_id` è impostato SIA sulla session SIA su
 * `subscription_data.metadata`, così gli eventi `checkout.session.completed`,
 * `invoice.*` e `customer.subscription.*` si riconducono tutti alla riga del dominio.
 */
final class StripeRecurringDonationCheckout implements ManagesRecurringDonation
{
    public function __construct(private readonly StripeConfig $config) {}

    public function createCheckout(RecurringDonation $recurring, string $successUrl, string $cancelUrl): DonationCheckoutSession
    {
        $client = $this->client();

        $recurringId = (string) $recurring->getKey();

        /** @var array<string, mixed> $params */
        $params = [
            'mode' => 'subscription',
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => $recurring->currency,
                    'unit_amount' => $recurring->amount,
                    'recurring' => [
                        'interval' => 'month',
                    ],
                    'product_data' => [
                        'name' => 'Donazione ricorrente a '.$recurring->organization->name,
                    ],
                ],
            ]],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => [
                'recurring_donation_id' => $recurringId,
            ],
            // Propaga l'id anche sulla subscription, così gli eventi invoice.*/
            // customer.subscription.* portano il metadata di riconciliazione.
            'subscription_data' => [
                'metadata' => [
                    'recurring_donation_id' => $recurringId,
                ],
            ],
        ];

        // Il donatore di una ricorrente è sempre autenticato: precompiliamo l'email per
        // la ricevuta e per associare il customer Stripe.
        $email = $recurring->donor->email;

        if ($email !== '') {
            $params['customer_email'] = $email;
        }

        /** @var Session $session */
        $session = $client->checkout->sessions->create($params);

        return new DonationCheckoutSession(
            id: (string) $session->id,
            url: (string) $session->url,
        );
    }

    public function cancel(string $stripeSubscriptionId): void
    {
        $this->client()->subscriptions->cancel($stripeSubscriptionId);
    }

    /**
     * Costruisce lo StripeClient dalla secret key effettiva. Backstop di configurazione:
     * l'implementazione non dà mai per scontata la configurazione.
     */
    private function client(): StripeClient
    {
        $secret = $this->config->secretKey();

        if ($secret === null) {
            throw DonationsNotConfigured::make();
        }

        return new StripeClient($secret);
    }
}
