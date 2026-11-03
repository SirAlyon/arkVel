<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Donations\ReconcileDonationFromStripe;
use App\Support\Billing\StripeConfig;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;

/**
 * Riceve i webhook di Stripe per il flusso donazioni (P4-6): POST /stripe/webhook.
 *
 * Controller SOTTILE: verifica la firma, estrae l'oggetto `session` dell'evento Checkout
 * e delega la riconciliazione di stato a {@see ReconcileDonationFromStripe} (unico punto
 * di transizione). Nessuna logica di dominio qui.
 *
 * SICUREZZA DELLA FIRMA (documentata di proposito):
 *  - Quando {@see StripeConfig::webhookSecret()} è VALORIZZATO, l'header `Stripe-Signature`
 *    è verificato con lo schema Stripe (`t=...,v1=hmac`): firma errata/assente → 400. È il
 *    comportamento di produzione, che impedisce a chiunque di falsificare un pagamento.
 *  - Quando il segreto è VUOTO (dev/test/installazione non ancora configurata del tutto) la
 *    verifica è SALTATA e il payload è decodificato così com'è. Questo permette lo sviluppo
 *    locale (Stripe CLI senza segreto) e i test senza firme reali. In produzione il segreto
 *    va impostato: documentato in {@see StripeConfig}.
 *
 * IDEMPOTENZA ed effetti collaterali isolati: Stripe può riconsegnare lo stesso evento;
 * la riconciliazione è idempotente (grafo di stato) e l'unico effetto è l'aggiornamento di
 * stato/paid_at della donazione. Eventi sconosciuti → 200 no-op (Stripe non deve ritentare).
 * La rotta è esclusa dalla CSRF: i webhook non portano token di sessione.
 */
final class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly StripeConfig $config,
        private readonly ReconcileDonationFromStripe $reconcile,
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

        /** @var array<string, mixed> $session */
        $session = $event['data']['object'] ?? [];

        $sessionId = is_string($session['id'] ?? null) ? $session['id'] : null;

        $metadataDonationId = $session['metadata']['donation_id'] ?? null;
        $donationId = is_numeric($metadataDonationId) ? (int) $metadataDonationId : null;

        // Delega: l'Action ignora gli eventi non pertinenti (no-op) ed è idempotente.
        $this->reconcile->handle($type, $sessionId, $donationId);

        // 200 sempre su payload accettato: evita ritentativi di Stripe per eventi che non
        // ci riguardano o già riconciliati.
        return response('OK', 200);
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
