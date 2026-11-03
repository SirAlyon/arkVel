<?php

declare(strict_types=1);

namespace App\Actions\Donations;

use App\Enums\RecurringDonationStatus;
use App\Models\RecurringDonation;
use App\Models\User;
use App\Support\Billing\ManagesRecurringDonation;
use Illuminate\Support\Facades\DB;

/**
 * Disdice una donazione ricorrente: disdice l'abbonamento su Stripe e porta la riga
 * RecurringDonation in stato Canceled (P4-7).
 *
 * Single responsibility: è l'unico punto in cui una ricorrente viene disdetta su
 * iniziativa del titolare (la disdetta "a valle" per subscription.deleted vive invece in
 * {@see ReconcileSubscriptionFromStripe}). L'AUTORIZZAZIONE (solo il proprietario) è
 * responsabilità della RecurringDonationPolicy, verificata dalla pagina /account PRIMA di
 * delegare qui: l'Action non è il confine di autorizzazione.
 *
 * IDEMPOTENTE: una ricorrente già Canceled è un no-op (nessuna chiamata a Stripe, nessuna
 * seconda transizione). La riga è bloccata in transazione (lockForUpdate) per serializzare
 * la disdetta con eventuali consegne webhook concorrenti. Il seam ({@see ManagesRecurringDonation})
 * è iniettato: nei test un fake deterministico senza rete.
 */
final class CancelRecurringDonation
{
    public function __construct(private readonly ManagesRecurringDonation $manager) {}

    /**
     * @param  User|null  $causer  utente che richiede la disdetta (per l'activity log)
     * @return bool true se la disdetta è stata applicata; false se era già disdetta (no-op)
     */
    public function handle(RecurringDonation $recurring, ?User $causer = null): bool
    {
        return DB::transaction(function () use ($recurring, $causer): bool {
            $fresh = RecurringDonation::query()
                ->whereKey($recurring->getKey())
                ->lockForUpdate()
                ->first();

            if ($fresh === null || $fresh->status === RecurringDonationStatus::Canceled) {
                // Già disdetta (o sparita): no-op idempotente, nessuna chiamata a Stripe.
                return false;
            }

            // Disdici su Stripe solo se l'abbonamento è già stato creato (id presente):
            // una ricorrente ancora Pending (webpayment non confermato) non ha subscription.
            if ($fresh->stripe_subscription_id !== null && $fresh->stripe_subscription_id !== '') {
                $this->manager->cancel($fresh->stripe_subscription_id);
            }

            if (! $fresh->status->canTransitionTo(RecurringDonationStatus::Canceled)) {
                return false;
            }

            $fresh->status = RecurringDonationStatus::Canceled;
            $fresh->canceled_at = now();
            $fresh->save();

            activity()
                ->performedOn($fresh)
                ->causedBy($causer)
                ->log('recurring_donation.canceled_by_donor');

            return true;
        });
    }
}
