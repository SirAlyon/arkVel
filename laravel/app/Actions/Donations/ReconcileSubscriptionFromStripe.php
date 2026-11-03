<?php

declare(strict_types=1);

namespace App\Actions\Donations;

use App\Enums\DonationStatus;
use App\Enums\DonationType;
use App\Enums\RecurringDonationStatus;
use App\Http\Controllers\StripeWebhookController;
use App\Models\Donation;
use App\Models\RecurringDonation;
use Illuminate\Support\Facades\DB;

/**
 * Riconcilia lo stato di una donazione RICORRENTE con un evento Stripe
 * subscription/invoice, riallineando il ciclo di vita della riga RecurringDonation e
 * registrando ogni addebito riuscito nel registro {@see Donation} (P4-7).
 *
 * Single responsibility: è l'unico punto in cui una ricorrente cambia stato e in cui
 * nasce un addebito ricorrente. Il controller del webhook ({@see StripeWebhookController})
 * resta sottile: verifica la firma, instrada per tipo/mode e passa qui l'oggetto evento.
 *
 * EVENTI gestiti:
 *  - checkout.session.completed (mode=subscription) → salva stripe_subscription_id e porta
 *    la ricorrente ad Active (started_at=now), se la transizione è consentita.
 *  - invoice.paid / invoice.payment_succeeded → crea la riga ledger Donation (recurring,
 *    Completed, provider_ref=invoice id, amount=amount_paid); se la ricorrente era PastDue
 *    la riporta Active. IDEMPOTENTE: una seconda consegna con lo stesso invoice id è no-op.
 *  - invoice.payment_failed → ricorrente → PastDue.
 *  - customer.subscription.deleted → ricorrente → Canceled, canceled_at=now.
 *  - altri eventi → no-op (ritorna false).
 *
 * IDEMPOTENZA e concorrenza: la riga RecurringDonation è bloccata in transazione
 * (lockForUpdate) per serializzare consegne concorrenti; le transizioni di stato sono
 * applicate solo se CONSENTITE dal grafo dell'enum, e il ledger è protetto dall'unicità
 * del provider_ref dell'invoice.
 *
 * MATCHING: la ricorrente è individuata per metadata.recurring_donation_id (impostato su
 * session e subscription) o, in fallback, per stripe_subscription_id. Nessuna corrispondenza
 * → no-op.
 */
final class ReconcileSubscriptionFromStripe
{
    /**
     * Applica l'esito dell'evento. `$object` è l'array `data.object` dell'evento Stripe
     * (session, invoice o subscription), su cui lavoriamo in modo agnostico come il
     * controller del webhook.
     *
     * @param  array<string, mixed>  $object
     * @return bool true se qualcosa è stato applicato; false se no-op
     */
    public function handle(string $eventType, array $object): bool
    {
        return match ($eventType) {
            'checkout.session.completed' => $this->activateFromSession($object),
            'invoice.paid', 'invoice.payment_succeeded' => $this->recordInvoicePaid($object),
            'invoice.payment_failed' => $this->markPastDue($object),
            'customer.subscription.deleted' => $this->markCanceled($object),
            default => false,
        };
    }

    /**
     * checkout.session.completed (mode=subscription): valorizza l'id dell'abbonamento e
     * attiva la ricorrente.
     *
     * @param  array<string, mixed>  $object
     */
    private function activateFromSession(array $object): bool
    {
        $subscriptionId = self::stringOrNull($object['subscription'] ?? null);
        $recurringId = self::recurringIdFromMetadata($object);

        return DB::transaction(function () use ($recurringId, $subscriptionId): bool {
            $recurring = $this->locate($recurringId, $subscriptionId);

            if ($recurring === null) {
                return false;
            }

            $changed = false;

            if ($subscriptionId !== null && $recurring->stripe_subscription_id === null) {
                $recurring->stripe_subscription_id = $subscriptionId;
                $changed = true;
            }

            if ($recurring->status->canTransitionTo(RecurringDonationStatus::Active)) {
                $recurring->status = RecurringDonationStatus::Active;
                $recurring->started_at ??= now();
                $changed = true;
            }

            if (! $changed) {
                return false;
            }

            $recurring->save();

            activity()
                ->performedOn($recurring)
                ->withProperties(['subscription_id' => $subscriptionId])
                ->log('recurring_donation.activated');

            return true;
        });
    }

    /**
     * invoice.paid / invoice.payment_succeeded: registra l'addebito nel ledger (idempotente
     * sul provider_ref dell'invoice) e recupera da PastDue ad Active.
     *
     * @param  array<string, mixed>  $object
     */
    private function recordInvoicePaid(array $object): bool
    {
        $invoiceId = self::stringOrNull($object['id'] ?? null);
        $subscriptionId = self::subscriptionIdFromInvoice($object);
        $recurringId = self::recurringIdFromMetadata($object);

        if ($invoiceId === null) {
            return false;
        }

        return DB::transaction(function () use ($object, $invoiceId, $subscriptionId, $recurringId): bool {
            $recurring = $this->locate($recurringId, $subscriptionId);

            if ($recurring === null) {
                return false;
            }

            // Idempotenza: lo stesso invoice non genera due righe. Il lockForUpdate sulla
            // ricorrente serializza le consegne concorrenti, rendendo sicuro questo check.
            $alreadyRecorded = Donation::query()
                ->where('provider_ref', $invoiceId)
                ->where('type', DonationType::Recurring)
                ->exists();

            if ($alreadyRecorded) {
                return false;
            }

            $donor = $recurring->donor;

            $amount = is_numeric($object['amount_paid'] ?? null)
                ? (int) $object['amount_paid']
                : $recurring->amount;

            $currency = self::stringOrNull($object['currency'] ?? null) ?? $recurring->currency;

            Donation::query()->create([
                'organization_id' => $recurring->organization_id,
                'recurring_donation_id' => $recurring->getKey(),
                'donor_user_id' => $recurring->user_id,
                'donor_name' => $donor->name,
                'donor_email' => $donor->email,
                'amount' => $amount,
                'currency' => $currency,
                'type' => DonationType::Recurring,
                'status' => DonationStatus::Completed,
                'provider' => 'stripe',
                'provider_ref' => $invoiceId,
                'paid_at' => now(),
            ]);

            // Un insoluto poi pagato torna attivo.
            if ($recurring->status === RecurringDonationStatus::PastDue) {
                $recurring->status = RecurringDonationStatus::Active;
                $recurring->save();
            }

            activity()
                ->performedOn($recurring)
                ->withProperties(['invoice_id' => $invoiceId, 'amount' => $amount])
                ->log('recurring_donation.charged');

            return true;
        });
    }

    /**
     * invoice.payment_failed: la ricorrente diventa insoluta.
     *
     * @param  array<string, mixed>  $object
     */
    private function markPastDue(array $object): bool
    {
        $subscriptionId = self::subscriptionIdFromInvoice($object);
        $recurringId = self::recurringIdFromMetadata($object);

        return $this->transition(
            $recurringId,
            $subscriptionId,
            RecurringDonationStatus::PastDue,
            'recurring_donation.past_due',
        );
    }

    /**
     * customer.subscription.deleted: la ricorrente è disdetta. Qui l'oggetto È
     * l'abbonamento, quindi l'id è object.id.
     *
     * @param  array<string, mixed>  $object
     */
    private function markCanceled(array $object): bool
    {
        $subscriptionId = self::stringOrNull($object['id'] ?? null);
        $recurringId = self::recurringIdFromMetadata($object);

        return $this->transition(
            $recurringId,
            $subscriptionId,
            RecurringDonationStatus::Canceled,
            'recurring_donation.canceled',
            markCanceledAt: true,
        );
    }

    /**
     * Applica una transizione di stato alla ricorrente individuata, solo se consentita.
     */
    private function transition(
        ?int $recurringId,
        ?string $subscriptionId,
        RecurringDonationStatus $target,
        string $logName,
        bool $markCanceledAt = false,
    ): bool {
        return DB::transaction(function () use ($recurringId, $subscriptionId, $target, $logName, $markCanceledAt): bool {
            $recurring = $this->locate($recurringId, $subscriptionId);

            if ($recurring === null || ! $recurring->status->canTransitionTo($target)) {
                return false;
            }

            $recurring->status = $target;

            if ($markCanceledAt) {
                $recurring->canceled_at = now();
            }

            $recurring->save();

            activity()->performedOn($recurring)->log($logName);

            return true;
        });
    }

    /**
     * Individua (e blocca) la ricorrente per metadata.recurring_donation_id o, in
     * fallback, per stripe_subscription_id. Il lockForUpdate serializza consegne concorrenti.
     */
    private function locate(?int $recurringId, ?string $subscriptionId): ?RecurringDonation
    {
        if ($recurringId !== null) {
            $byId = RecurringDonation::query()
                ->whereKey($recurringId)
                ->lockForUpdate()
                ->first();

            if ($byId !== null) {
                return $byId;
            }
        }

        if ($subscriptionId !== null && $subscriptionId !== '') {
            return RecurringDonation::query()
                ->where('stripe_subscription_id', $subscriptionId)
                ->lockForUpdate()
                ->first();
        }

        return null;
    }

    /**
     * Estrae l'id della subscription da un invoice: campo diretto `subscription`
     * (schema classico) o, in fallback, `parent.subscription_details.subscription`
     * (schema recente dell'API Stripe).
     *
     * @param  array<string, mixed>  $object
     */
    private static function subscriptionIdFromInvoice(array $object): ?string
    {
        $direct = self::stringOrNull($object['subscription'] ?? null);

        if ($direct !== null) {
            return $direct;
        }

        $parent = $object['parent'] ?? null;

        if (is_array($parent)) {
            $details = $parent['subscription_details'] ?? null;

            if (is_array($details)) {
                return self::stringOrNull($details['subscription'] ?? null);
            }
        }

        return null;
    }

    /**
     * Estrae metadata.recurring_donation_id (quando presente) come intero.
     *
     * @param  array<string, mixed>  $object
     */
    private static function recurringIdFromMetadata(array $object): ?int
    {
        $metadata = $object['metadata'] ?? null;

        if (! is_array($metadata)) {
            return null;
        }

        $value = $metadata['recurring_donation_id'] ?? null;

        return is_numeric($value) ? (int) $value : null;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return (is_string($value) && $value !== '') ? $value : null;
    }
}
