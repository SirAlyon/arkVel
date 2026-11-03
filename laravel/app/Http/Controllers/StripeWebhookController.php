<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Donations\ReconcileDonationFromStripe;
use App\Actions\Donations\ReconcileSubscriptionFromStripe;
use App\Support\Billing\StripeConfig;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

/**
 * Riceve i webhook di Stripe per i flussi donazioni (P4-6 una tantum / P4-7 ricorrenti):
 * POST /stripe/webhook.
 *
 * Controller SOTTILE: verifica la firma, estrae l'oggetto evento e INSTRADA alla Action di
 * riconciliazione giusta (unico punto di transizione per ciascun flusso). Nessuna logica
 * di dominio qui.
 *
 * INSTRADAMENTO (una tantum vs ricorrente, senza collisioni):
 *  - checkout.session.* → si RAMIFICA su `session.mode`:
 *      · mode='subscription' → {@see ReconcileSubscriptionFromStripe} (attivazione ricorrente);
 *      · altrimenti (mode='payment' o assente) → {@see ReconcileDonationFromStripe} (una tantum).
 *  - invoice.* e customer.subscription.* → {@see ReconcileSubscriptionFromStripe}.
 *  - ogni altro evento → inoltrato all'Action una tantum, che fa no-op sugli eventi non
 *    pertinenti (comportamento P4-6 invariato).
 *
 * SICUREZZA DELLA FIRMA (documentata di proposito):
 *  - Quando {@see StripeConfig::webhookSecret()} è VALORIZZATO, l'header `Stripe-Signature`
 *    è verificato con lo schema Stripe (`t=...,v1=hmac`): firma errata/assente → 400. È il
 *    comportamento di produzione, che impedisce a chiunque di falsificare un pagamento.
 *  - Quando il segreto è VUOTO (dev/test/installazione non ancora configurata del tutto) la
 *    verifica è SALTATA e il payload è decodificato così com'è. In produzione il segreto
 *    va impostato: documentato in {@see StripeConfig}.
 *
 * IDEMPOTENZA ed effetti collaterali isolati: Stripe può riconsegnare lo stesso evento; le
 * Action di riconciliazione sono idempotenti (grafo di stato + unicità del provider_ref).
 * Eventi sconosciuti → 200 no-op. La rotta è esclusa dalla CSRF: i webhook non portano
 * token di sessione.
 */
final class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly StripeConfig $config,
        private readonly ReconcileDonationFromStripe $reconcileDonation,
        private readonly ReconcileSubscriptionFromStripe $reconcileSubscription,
    ) {}

    public function __invoke(Request $request): Response
    {
        $payload = $request->getContent();

        $event = $this->resolveEvent($payload, $request->header('Stripe-Signature'));

        if ($event === null) {
            // Firma non valida: respingi (Stripe ritenterà). Nessun dato toccato.
            return response('Invalid signature', 400);
        }

        $type = is_string($event['type'] ?? null) ? $event['type'] : '';

        /** @var array<string, mixed> $object */
        $object = $event['data']['object'] ?? [];

        $this->route($type, $object);

        // 200 sempre su payload accettato: evita ritentativi di Stripe per eventi che non
        // ci riguardano o già riconciliati.
        return response('OK', 200);
    }

    /**
     * Instrada l'evento alla riconciliazione corretta. Delega idempotente: le Action
     * ignorano gli eventi non pertinenti (no-op).
     *
     * @param  array<string, mixed>  $object
     */
    private function route(string $type, array $object): void
    {
        $isSubscriptionLifecycle = str_starts_with($type, 'invoice.')
            || str_starts_with($type, 'customer.subscription.');

        if ($isSubscriptionLifecycle) {
            $this->reconcileSubscription->handle($type, $object);

            return;
        }

        if (str_starts_with($type, 'checkout.session.')
            && (is_string($object['mode'] ?? null) ? $object['mode'] : null) === 'subscription') {
            $this->reconcileSubscription->handle($type, $object);

            return;
        }

        // Flusso una tantum (checkout.session.* mode payment/assente, o eventi ignoti): il
        // matching avviene per id sessione o metadata.donation_id.
        $sessionId = is_string($object['id'] ?? null) ? $object['id'] : null;

        $metadataDonationId = $object['metadata']['donation_id'] ?? null;
        $donationId = is_numeric($metadataDonationId) ? (int) $metadataDonationId : null;

        $this->reconcileDonation->handle($type, $sessionId, $donationId);
    }

    /**
     * Decodifica l'evento, verificando la firma SOLO se il webhook secret è impostato.
     * Ritorna l'array dell'evento, o null se la firma è richiesta ma non valida.
     *
     * @return array<string, mixed>|null
     */
    private function resolveEvent(string $payload, ?string $signature): ?array
    {
        $secret = $this->config->webhookSecret();

        // Segreto vuoto (dev/test/non configurato): nessuna verifica, decodifica diretta.
        if ($secret === null || $secret === '') {
            /** @var array<string, mixed>|null $decoded */
            $decoded = json_decode($payload, true);

            return is_array($decoded) ? $decoded : null;
        }

        if ($signature === null) {
            return null;
        }

        try {
            $event = Webhook::constructEvent($payload, $signature, $secret);
        } catch (SignatureVerificationException) {
            return null;
        }

        // L'oggetto StripeObject è arrayizzabile: lavoriamo su array per restare agnostici.
        /** @var array<string, mixed> $array */
        $array = $event->toArray();

        return $array;
    }
}
