<?php

declare(strict_types=1);

namespace App\Actions\Donations;

use App\Enums\RecurringDonationStatus;
use App\Exceptions\DonationsNotConfigured;
use App\Exceptions\InvalidDonationAmount;
use App\Exceptions\MissingDonorIdentity;
use App\Models\Organization;
use App\Models\RecurringDonation;
use App\Models\User;
use App\Support\Billing\ManagesRecurringDonation;
use App\Support\Billing\StripeConfig;
use Illuminate\Support\Facades\DB;

/**
 * Avvia una donazione RICORRENTE (mensile) verso un ente: crea la riga RecurringDonation
 * in stato Pending e apre una sessione di Stripe Checkout `mode=subscription`,
 * restituendo l'URL su cui redirigere il donatore (P4-7).
 *
 * Single responsibility: è l'unico punto in cui una ricorrente nasce. Le guardie di
 * dominio sono valutate PRIMA di scrivere (e prima di toccare Stripe), così un rifiuto non
 * lascia righe orfane:
 *  1. Stripe dev'essere configurato ({@see StripeConfig::configured()}) → DonationsNotConfigured;
 *  2. l'importo dev'essere nei limiti ({@see StartDonationCheckout::MIN_AMOUNT}..MAX_AMOUNT) → InvalidDonationAmount;
 *  3. il donatore DEV'essere un utente autenticato → MissingDonorIdentity. A differenza
 *     della una tantum, le ricorrenti NON sono per ospiti: serve un account per
 *     gestirle/disdirle dal pannello /account.
 *
 * La riga nasce Pending con `stripe_subscription_id` null: diventa Active (e riceve l'id
 * dell'abbonamento) SOLO quando il webhook `checkout.session.completed` conferma
 * l'attivazione ({@see ReconcileSubscriptionFromStripe}). Il metadata.recurring_donation_id
 * è il ponte di riconciliazione per tutti gli eventi subscription/invoice.
 *
 * Il seam ({@see ManagesRecurringDonation}) è iniettato: in produzione chiama Stripe, nei
 * test un fake deterministico senza rete (Dependency Inversion).
 */
final class StartRecurringDonationCheckout
{
    public function __construct(
        private readonly StripeConfig $stripeConfig,
        private readonly ManagesRecurringDonation $checkout,
    ) {}

    /**
     * @param  User|null  $donor  donatore autenticato (OBBLIGATORIO per le ricorrenti)
     * @return string URL ospitato della sessione di Checkout su cui redirigere
     *
     * @throws DonationsNotConfigured quando Stripe non è configurato
     * @throws InvalidDonationAmount quando l'importo è fuori dai limiti
     * @throws MissingDonorIdentity quando manca il donatore autenticato
     */
    public function handle(
        Organization $organization,
        int $amountCents,
        string $currency,
        ?User $donor = null,
    ): string {
        if (! $this->stripeConfig->configured()) {
            throw DonationsNotConfigured::make();
        }

        if ($amountCents < StartDonationCheckout::MIN_AMOUNT || $amountCents > StartDonationCheckout::MAX_AMOUNT) {
            throw InvalidDonationAmount::outOfRange(
                $amountCents,
                StartDonationCheckout::MIN_AMOUNT,
                StartDonationCheckout::MAX_AMOUNT,
            );
        }

        // Le ricorrenti richiedono un account: senza un utente autenticato si rifiuta
        // prima di scrivere.
        if ($donor === null) {
            throw MissingDonorIdentity::make();
        }

        return DB::transaction(function () use ($organization, $amountCents, $currency, $donor): string {
            $recurring = RecurringDonation::query()->create([
                'user_id' => $donor->getKey(),
                'organization_id' => $organization->getKey(),
                'amount' => $amountCents,
                'currency' => $currency,
                'interval' => 'monthly',
                'status' => RecurringDonationStatus::Pending,
            ]);

            // Carica le relazioni usate dal seam (nome prodotto + email) senza query extra.
            $recurring->setRelation('organization', $organization);
            $recurring->setRelation('donor', $donor);

            $session = $this->checkout->createCheckout(
                $recurring,
                route('organizations.donate.thanks', ['organization' => $organization->slug]),
                route('organizations.donate.cancel', ['organization' => $organization->slug]),
            );

            activity()
                ->performedOn($recurring)
                ->causedBy($donor)
                ->withProperties([
                    'amount' => $amountCents,
                    'currency' => $currency,
                    'session_id' => $session->id,
                ])
                ->log('recurring_donation.checkout_started');

            return $session->url;
        });
    }
}
