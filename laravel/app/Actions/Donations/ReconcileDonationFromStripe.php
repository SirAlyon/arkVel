<?php

declare(strict_types=1);

namespace App\Actions\Donations;

use App\Enums\DonationStatus;
use App\Http\Controllers\StripeWebhookController;
use App\Models\Donation;
use Illuminate\Support\Facades\DB;

/**
 * Riconcilia lo stato di una donazione con un evento di Stripe Checkout, riallineando il
 * ciclo di vita della riga Donation all'esito del pagamento.
 *
 * Single responsibility: è l'unico punto in cui una donazione cambia stato a seguito di
 * un evento Stripe. Il controller del webhook ({@see StripeWebhookController})
 * resta sottile: verifica la firma, estrae l'oggetto `session` e delega qui.
 *
 * EVENTI gestiti (mappa evento → stato):
 *  - checkout.session.completed            → Completed (pagamento con carta riuscito)
 *  - checkout.session.async_payment_succeeded → Completed (SEPA assestato più tardi)
 *  - checkout.session.expired              → Failed    (sessione scaduta non pagata)
 *  - checkout.session.async_payment_failed → Failed    (SEPA rifiutato)
 *
 * IDEMPOTENZA: Stripe può consegnare lo stesso evento più volte. La transizione è
 * applicata solo se CONSENTITA dal grafo dell'enum ({@see DonationStatus::canTransitionTo()}):
 * una donazione già Completed ignora una seconda `completed` (no-op), e una già Completed
 * non regredisce a Failed per un `expired` in ritardo. La riga è bloccata in transazione
 * (lockForUpdate) per serializzare consegne concorrenti (TOCTOU chiuso).
 *
 * MATCHING: la donazione è individuata per provider_ref (id sessione) o, in fallback, per
 * metadata.donation_id. Nessuna donazione corrispondente → no-op (l'evento non riguarda
 * questa installazione / una donazione non più esistente).
 */
final class ReconcileDonationFromStripe
{
    /**
     * Applica l'esito dell'evento alla donazione individuata.
     *
     * @param  string  $eventType  tipo evento Stripe (es. `checkout.session.completed`)
     * @param  string|null  $sessionId  id della sessione Checkout (session.id)
     * @param  int|null  $donationId  metadata.donation_id dell'evento, se presente
     * @return bool true se una transizione è stata applicata; false se no-op
     */
    public function handle(string $eventType, ?string $sessionId, ?int $donationId): bool
    {
        $target = self::targetFor($eventType);

        if ($target === null) {
            // Evento non pertinente al ciclo di vita delle donazioni: no-op.
            return false;
        }

        return DB::transaction(function () use ($sessionId, $donationId, $target): bool {
            $donation = $this->locate($sessionId, $donationId);

            if ($donation === null) {
                return false;
            }

            // Idempotenza: applica solo se la transizione è consentita dal grafo. Una
            // seconda consegna dello stesso esito (già nello stato target) è no-op.
            if (! $donation->status->canTransitionTo($target)) {
                return false;
            }

            $donation->status = $target;

            if ($target === DonationStatus::Completed) {
                $donation->paid_at = now();
            }

            $donation->save();

            activity()
                ->performedOn($donation)
                ->withProperties([
                    'status' => $target->value,
                    'session_id' => $sessionId,
                ])
                ->log('donation.reconciled');

            return true;
        });
    }

    /**
     * Stato bersaglio per un tipo di evento, o null se l'evento non ci riguarda.
     */
    private static function targetFor(string $eventType): ?DonationStatus
    {
        return match ($eventType) {
            'checkout.session.completed',
            'checkout.session.async_payment_succeeded' => DonationStatus::Completed,
            'checkout.session.expired',
            'checkout.session.async_payment_failed' => DonationStatus::Failed,
            default => null,
        };
    }

    /**
     * Individua (e blocca) la donazione per id sessione o, in fallback, per
     * metadata.donation_id. Il lockForUpdate serializza consegne concorrenti.
     */
    private function locate(?string $sessionId, ?int $donationId): ?Donation
    {
        if ($sessionId !== null && $sessionId !== '') {
            $bySession = Donation::query()
                ->where('provider_ref', $sessionId)
                ->lockForUpdate()
                ->first();

            if ($bySession !== null) {
                return $bySession;
            }
        }

        if ($donationId !== null) {
            return Donation::query()
                ->whereKey($donationId)
                ->lockForUpdate()
                ->first();
        }

        return null;
    }
}
