<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Billing\CreatesDonationCheckout;
use App\Support\Billing\ManagesRecurringDonation;
use App\Support\Billing\StripeConfig;
use App\Support\Billing\StripeDonationCheckout;
use App\Support\Billing\StripeRecurringDonationCheckout;
use App\Support\Settings\PlatformSettings;
use Illuminate\Support\ServiceProvider;

/**
 * Applica a RUNTIME le credenziali Stripe salvate da UI admin (DB) sopra la config
 * Cashier letta dall'.env, così lo SDK Stripe/Cashier usa sempre le chiavi effettive
 * risolte da {@see StripeConfig} (DB-over-env).
 *
 * Perché un provider dedicato e non AppServiceProvider: tiene isolato il concern
 * "billing" (Single Responsibility) e lascia AppServiceProvider al dominio generale,
 * esattamente come {@see CalendarSyncServiceProvider} isola il concern "calendario".
 *
 * ROBUSTEZZA (stesso pattern tollerante di PlatformSettings): la lettura del DB degrada
 * a "assente" se la tabella platform_settings non esiste ancora (pre-migrate) o se le
 * chiavi non sono impostate. In quel caso il metodo è un no-op e la config resta quella
 * dell'.env: nessuna eccezione al bootstrap, nessun flusso di pagamento attivato per
 * sbaglio (il config-gating resta in {@see StripeConfig::configured()}).
 */
class BillingServiceProvider extends ServiceProvider
{
    /**
     * Binding del seam di Checkout (Dependency Inversion): l'Action dipende dalla porta
     * {@see CreatesDonationCheckout}, in produzione servita dall'impl Stripe. Nei test si
     * sostituisce con un fake deterministico (nessuna rete). Default `bind` (non
     * singleton): lo StripeClient è costruito a ogni chiamata dalla config effettiva.
     */
    public function register(): void
    {
        $this->app->bind(CreatesDonationCheckout::class, StripeDonationCheckout::class);

        // Seam delle donazioni RICORRENTI (P4-7): stessa inversione di dipendenza, in
        // produzione servita dall'impl Stripe (Checkout mode=subscription + disdetta).
        // Nei test si sostituisce con un fake deterministico (nessuna rete).
        $this->app->bind(ManagesRecurringDonation::class, StripeRecurringDonationCheckout::class);
    }

    public function boot(): void
    {
        $stripe = new StripeConfig(app(PlatformSettings::class));

        // Solo override NON-null: se il DB non ha la chiave, StripeConfig ha già ricaduto
        // sull'.env, quindi riscrivere la stessa config sarebbe inutile. Scriviamo solo i
        // valori effettivamente presenti per non azzerare l'.env con dei null.
        $this->overrideWhenPresent('cashier.key', $stripe->publishableKey());
        $this->overrideWhenPresent('cashier.secret', $stripe->secretKey());
        $this->overrideWhenPresent('cashier.webhook.secret', $stripe->webhookSecret());
    }

    private function overrideWhenPresent(string $configPath, ?string $value): void
    {
        if (filled($value)) {
            config([$configPath => $value]);
        }
    }
}
