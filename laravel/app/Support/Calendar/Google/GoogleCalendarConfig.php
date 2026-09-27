<?php

declare(strict_types=1);

namespace App\Support\Calendar\Google;

use App\Support\Settings\PlatformSettings;

/**
 * Risolve le credenziali dell'app OAuth Google Calendar a livello di PIATTAFORMA
 * (una sola app per l'intera istanza; ogni ENTE collega poi il proprio account).
 *
 * Precedenza: il valore salvato da UI in {@see PlatformSettings} VINCE sull'.env.
 * Se la chiave DB è assente/vuota si ricade sulla config `services.google_calendar.*`
 * (l'.env). Così un operatore può configurare tutto da interfaccia senza toccare il
 * server, ma un deploy che imposta solo l'.env continua a funzionare identico.
 *
 * Nessuna logica OAuth qui: solo risoluzione dei tre valori + due predicati di stato
 * per la UI ({@see configured()}, {@see source()}).
 */
class GoogleCalendarConfig
{
    public const string KEY_CLIENT_ID = 'google_calendar.client_id';

    public const string KEY_CLIENT_SECRET = 'google_calendar.client_secret';

    public const string KEY_REDIRECT_URI = 'google_calendar.redirect_uri';

    public function __construct(private readonly PlatformSettings $settings) {}

    /**
     * Client ID: valore DB se presente, altrimenti l'.env, altrimenti null.
     */
    public function clientId(): ?string
    {
        return $this->resolve(self::KEY_CLIENT_ID, 'services.google_calendar.client_id');
    }

    /**
     * Client secret: valore DB se presente, altrimenti l'.env, altrimenti null.
     */
    public function clientSecret(): ?string
    {
        return $this->resolve(self::KEY_CLIENT_SECRET, 'services.google_calendar.client_secret');
    }

    /**
     * Redirect URI registrato in Google Cloud Console: valore DB se presente,
     * altrimenti l'.env, altrimenti null. Quando è null, {@see GoogleOAuth} ricade
     * sulla rotta nominata `calendar.google.callback`.
     */
    public function redirectUri(): ?string
    {
        return $this->resolve(self::KEY_REDIRECT_URI, 'services.google_calendar.redirect');
    }

    /**
     * L'integrazione è configurata quando sia client_id sia client_secret sono valorizzati
     * (da DB o da .env). Predicato unico del config-gating a monte.
     */
    public function configured(): bool
    {
        return filled($this->clientId()) && filled($this->clientSecret());
    }

    /**
     * Origine della configurazione EFFETTIVA, per la UI:
     *  - 'db'  se almeno una tra client_id/client_secret arriva dal DB (override attivo);
     *  - 'env' se le credenziali ci sono ma solo dall'.env;
     *  - 'none' se non è configurato affatto.
     */
    public function source(): string
    {
        if (! $this->configured()) {
            return 'none';
        }

        $fromDb = filled($this->settings->get(self::KEY_CLIENT_ID))
            || filled($this->settings->get(self::KEY_CLIENT_SECRET));

        return $fromDb ? 'db' : 'env';
    }

    /**
     * Il DB ha almeno un valore per una delle chiavi Google (override presente).
     */
    public function hasStoredClientSecret(): bool
    {
        return filled($this->settings->get(self::KEY_CLIENT_SECRET));
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
