<?php

declare(strict_types=1);

namespace App\Support\Billing;

use App\Exceptions\DonationsNotConfigured;
use App\Models\Donation;
use Stripe\Checkout\Session;
use Stripe\StripeClient;

/**
 * Implementazione reale del seam {@see CreatesDonationCheckout}: crea una sessione di
 * Checkout Stripe (modalità `payment`, una tantum) con lo SDK Stripe incluso in Cashier.
 *
 * Costruisce il client dalla secret key risolta da {@see StripeConfig} (DB-over-env). Se
 * la secret key è assente solleva {@see DonationsNotConfigured}: è un backstop: l'Action
 * verifica già `StripeConfig::configured()` a monte, ma l'implementazione non deve mai
 * dare per scontata la configurazione.
 *
 * Metodi di pagamento: carta + addebito SEPA (bonifico diretto europeo). Il SEPA si
 * "assesta" in modo asincrono: la donazione resta Pending finché Stripe non invia
 * `checkout.session.async_payment_succeeded` (gestito dal webhook, P4-6 STEP 3).
 *
 * Il `metadata.donation_id` lega l'evento webhook alla riga Donation anche se l'id della
 * sessione cambiasse; l'importo è passato in CENTESIMI (già la fonte di verità del model),
 * con `price_data` inline — niente catalogo prodotti su Stripe per le donazioni.
 */
final class StripeDonationCheckout implements CreatesDonationCheckout
{
    public function __construct(private readonly StripeConfig $config) {}

    public function create(Donation $donation, string $successUrl, string $cancelUrl): DonationCheckoutSession
    {
        $secret = $this->config->secretKey();

        if ($secret === null) {
            throw DonationsNotConfigured::make();
        }

        $client = new StripeClient($secret);

        /** @var array<string, mixed> $params */
        $params = [
            'mode' => 'payment',
            'payment_method_types' => ['card', 'sepa_debit'],
            'line_items' => [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => $donation->currency,
                    'unit_amount' => $donation->amount,
                    'product_data' => [
                        'name' => 'Donazione a '.$donation->organization->name,
                    ],
                ],
            ]],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'metadata' => [
                'donation_id' => (string) $donation->getKey(),
            ],
        ];

        // Email precompilata quando il donatore è noto (autenticato o ospite con email):
        // Stripe la usa per la ricevuta e per associare l'eventuale cliente.
        $email = $donation->donor_email;

        if ($email !== null && $email !== '') {
            $params['customer_email'] = $email;
        }

        /** @var Session $session */
        $session = $client->checkout->sessions->create($params);

        return new DonationCheckoutSession(
            id: (string) $session->id,
            url: (string) $session->url,
        );
    }
}
