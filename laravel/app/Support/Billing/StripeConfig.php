<?php

declare(strict_types=1);

namespace App\Support\Billing;

use App\Providers\BillingServiceProvider;
use App\Support\Calendar\Google\GoogleCalendarConfig;
use App\Support\Settings\PlatformSettings;

/**
 * Risolve le credenziali Stripe a livello di PIATTAFORMA.
 *
 * ARCHITETTURA (decisa in P4-5): UN SOLO account Stripe per l'intera istanza ArkVel
 * (le chiavi valgono per tutta la piattaforma, esattamente come l'app OAuth Google in
 * {@see GoogleCalendarConfig}); le donazioni sono poi
 * registrate PER-ENTE sul model Donation. I payout per-ente via Stripe Connect sono
 * FUORI SCOPE / enhancement futuro: qui NON si costruisce alcun account connesso.
 *
 * Precedenza: il valore salvato da UI admin in {@see PlatformSettings} VINCE sull'.env.
 * Se la chiave DB è assente/vuota si ricade sulla config `cashier.*` (l'.env). Così un
 * operatore può configurare Stripe da interfaccia (P4-8) senza toccare il server, ma un
 * deploy che imposta solo l'.env continua a funzionare identico.
 *
 * Nessuna logica di pagamento qui: solo risoluzione dei tre valori + due predicati di
 * stato per la UI ({@see configured()}, {@see source()}). L'applicazione a runtime della
 * config Cashier avviene in {@see BillingServiceProvider}.
 */
class StripeConfig
{
    public const string KEY_PUBLISHABLE = 'stripe.publishable_key';

    public const string KEY_SECRET = 'stripe.secret_key';

    public const string KEY_WEBHOOK_SECRET = 'stripe.webhook_secret';

    public function __construct(private readonly PlatformSettings $settings) {}

    /**
     * Chiave pubblicabile (publishable key): valore DB se presente, altrimenti l'.env,
     * altrimenti null.
     */
    public function publishableKey(): ?string
    {
        return $this->resolve(self::KEY_PUBLISHABLE, 'cashier.key');
    }

    /**
     * Chiave segreta (secret key): valore DB se presente, altrimenti l'.env, altrimenti null.
     */
    public function secretKey(): ?string
    {
        return $this->resolve(self::KEY_SECRET, 'cashier.secret');
    }

    /**
     * Segreto di firma dei webhook: valore DB se presente, altrimenti l'.env, altrimenti null.
     */
    public function webhookSecret(): ?string
    {
        return $this->resolve(self::KEY_WEBHOOK_SECRET, 'cashier.webhook.secret');
    }

    /**
     * L'integrazione è configurata quando sia la publishable sia la secret key sono
     * valorizzate (da DB o da .env). Predicato unico del config-gating a monte: finché
     * è false, nessun flusso Checkout/webhook (P4-6/P4-7) deve essere esposto.
     */
    public function configured(): bool
    {
        return filled($this->publishableKey()) && filled($this->secretKey());
    }

    /**
     * Origine della configurazione EFFETTIVA, per la UI:
     *  - 'db'  se almeno una tra publishable/secret arriva dal DB (override attivo);
     *  - 'env' se le credenziali ci sono ma solo dall'.env;
     *  - 'none' se non è configurato affatto.
     */
    public function source(): string
    {
        if (! $this->configured()) {
            return 'none';
        }

        $fromDb = filled($this->settings->get(self::KEY_PUBLISHABLE))
            || filled($this->settings->get(self::KEY_SECRET));

        return $fromDb ? 'db' : 'env';
    }

    /**
     * Il DB ha un valore per la secret key (override presente). Utile alla UI admin
     * (P4-8) per mostrare "chiave salvata" senza esporre il segreto in chiaro.
     */
    public function hasStoredSecretKey(): bool
    {
        return filled($this->settings->get(self::KEY_SECRET));
    }

    /**
     * Il DB ha un valore per il webhook secret (override presente). Come
     * {@see hasStoredSecretKey()}: la UI admin (P4-8) mostra "segreto salvato" senza mai
     * ripresentare il valore al browser.
     */
    public function hasStoredWebhookSecret(): bool
    {
        return filled($this->settings->get(self::KEY_WEBHOOK_SECRET));
    }

    /**
     * Legge la chiave DB; se vuota/assente ricade sul path di config (.env).
     */
    private function resolve(string $key, string $configPath): ?string
    {
        $stored = $this->settings->get($key);

        if (filled($stored)) {
            return $stored;
        }

        $fallback = config($configPath);

        return (is_string($fallback) && $fallback !== '') ? $fallback : null;
    }
}
