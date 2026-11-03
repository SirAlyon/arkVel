<?php

declare(strict_types=1);

namespace App\Actions\Donations;

use App\Enums\DonationStatus;
use App\Enums\DonationType;
use App\Exceptions\DonationsNotConfigured;
use App\Exceptions\InvalidDonationAmount;
use App\Exceptions\MissingDonorIdentity;
use App\Models\Donation;
use App\Models\Organization;
use App\Models\User;
use App\Support\Billing\CreatesDonationCheckout;
use App\Support\Billing\StripeConfig;
use Illuminate\Support\Facades\DB;

/**
 * Avvia una donazione UNA TANTUM verso un ente: crea la riga Donation in stato Pending e
 * apre una sessione di Stripe Checkout, restituendo l'URL su cui redirigere il donatore.
 *
 * Single responsibility: è l'unico punto in cui una donazione nasce. Le guardie di
 * dominio sono valutate PRIMA di scrivere (e prima di toccare Stripe), così un rifiuto non
 * lascia righe orfane:
 *  1. Stripe dev'essere configurato ({@see StripeConfig::configured()}) → DonationsNotConfigured;
 *  2. l'importo dev'essere nei limiti ({@see MIN_AMOUNT}..{@see MAX_AMOUNT}) → InvalidDonationAmount;
 *  3. dev'esistere un'identità donatore (utente loggato O email d'ospite) → MissingDonorIdentity.
 *
 * La riga nasce Pending: diventa Completed SOLO quando il webhook Stripe conferma il
 * pagamento (P4-6 STEP 3). Il provider_ref = id della sessione Checkout è il ponte di
 * riconciliazione (indicizzato sul model), insieme a metadata.donation_id.
 *
 * Il seam di Checkout ({@see CreatesDonationCheckout}) è iniettato: in produzione chiama
 * Stripe, nei test un fake deterministico senza rete (Dependency Inversion).
 */
final class StartDonationCheckout
{
    /** Importo minimo in centesimi (1€): copre le soglie minime di Stripe. */
    public const int MIN_AMOUNT = 100;

    /** Importo massimo in centesimi (10.000€): anti-abuso per il form pubblico. */
    public const int MAX_AMOUNT = 1_000_000;

    public function __construct(
        private readonly StripeConfig $stripeConfig,
        private readonly CreatesDonationCheckout $checkout,
    ) {}

    /**
     * @param  User|null  $donor  donatore autenticato, oppure null per una donazione d'ospite
     * @param  string|null  $donorName  nome d'ospite (ignorato se c'è un utente)
     * @param  string|null  $donorEmail  email d'ospite (obbligatoria senza utente)
     * @return string URL ospitato della sessione di Checkout su cui redirigere
     *
     * @throws DonationsNotConfigured quando Stripe non è configurato
     * @throws InvalidDonationAmount quando l'importo è fuori dai limiti
     * @throws MissingDonorIdentity quando manca sia l'utente sia l'email d'ospite
     */
    public function handle(
        Organization $organization,
        int $amountCents,
        string $currency,
        ?User $donor = null,
        ?string $donorName = null,
        ?string $donorEmail = null,
        ?string $message = null,
    ): string {
        if (! $this->stripeConfig->configured()) {
            throw DonationsNotConfigured::make();
        }

        if ($amountCents < self::MIN_AMOUNT || $amountCents > self::MAX_AMOUNT) {
            throw InvalidDonationAmount::outOfRange($amountCents, self::MIN_AMOUNT, self::MAX_AMOUNT);
        }

        // Identità donatore: l'utente autenticato porta nome/email dal proprio account;
        // l'ospite DEVE fornire un'email (nome facoltativo). Senza nessuno dei due si
        // rifiuta prima di scrivere.
        $resolvedName = $donor !== null
            ? $donor->name
            : ($donorName !== null && $donorName !== '' ? $donorName : null);

        $resolvedEmail = $donor !== null
            ? $donor->email
            : ($donorEmail !== null && $donorEmail !== '' ? $donorEmail : null);

        if ($donor === null && $resolvedEmail === null) {
            throw MissingDonorIdentity::make();
        }

        return DB::transaction(function () use (
            $organization,
            $amountCents,
            $currency,
            $donor,
            $resolvedName,
            $resolvedEmail,
            $message,
        ): string {
            $donation = Donation::query()->create([
                'organization_id' => $organization->getKey(),
                'donor_user_id' => $donor?->getKey(),
                'donor_name' => $resolvedName,
                'donor_email' => $resolvedEmail,
                'amount' => $amountCents,
                'currency' => $currency,
                'type' => DonationType::OneOff,
                'status' => DonationStatus::Pending,
                'provider' => 'stripe',
                'message' => ($message !== null && $message !== '') ? $message : null,
            ]);

            // Carica la relazione per il nome prodotto nel Checkout senza una query
            // aggiuntiva lato seam.
            $donation->setRelation('organization', $organization);

            $session = $this->checkout->create(
                $donation,
                route('organizations.donate.thanks', ['organization' => $organization->slug]),
                route('organizations.donate.cancel', ['organization' => $organization->slug]),
            );

            $donation->provider_ref = $session->id;
            $donation->save();

            activity()
                ->performedOn($donation)
                ->causedBy($donor)
                ->withProperties([
                    'amount' => $amountCents,
                    'currency' => $currency,
                    'session_id' => $session->id,
                ])
                ->log('donation.checkout_started');

            return $session->url;
        });
    }
}
