<?php

declare(strict_types=1);

namespace App\Support\Calendar\Google;

use App\Exceptions\CalendarProviderNotConfigured;
use Illuminate\Support\Facades\Http;

/**
 * Client OAuth 2.0 di Google, scritto a mano su Laravel {@see Http} — NESSUNA SDK
 * (google/apiclient), così ogni chiamata è interamente falsabile con `Http::fake()` nei
 * test e la dipendenza resta minima.
 *
 * Copre le tre operazioni del flusso Authorization Code:
 *  - {@see authorizationUrl()} — l'URL a cui reindirizzare l'utente per il consenso;
 *  - {@see exchangeCode()} — scambio del `code` con access/refresh token (prima volta);
 *  - {@see refresh()} — rinnovo dell'access token dal refresh token.
 *
 * CONFIG-GATING: se le credenziali OAuth non sono impostate (client_id/client_secret
 * vuoti) i metodi di RETE sollevano {@see CalendarProviderNotConfigured}. La funzione
 * resta inerte finché l'operatore non configura le env — nessun segreto vive nel repo.
 *
 * Lo scope richiesto è il solo `calendar.events`: ArkVel scrive/aggiorna EVENTI, non
 * gestisce calendari — principio del minimo privilegio.
 */
final class GoogleOAuth
{
    private const string AUTH_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const string TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    /**
     * Solo la scrittura di eventi: nessun accesso alla gestione dei calendari o ad
     * altre API Google (minimo privilegio).
     */
    public const string SCOPE = 'https://www.googleapis.com/auth/calendar.events';

    /**
     * Le credenziali dell'app OAuth arrivano SEMPRE dal resolver, che dà precedenza ai
     * valori salvati da UI (DB) sull'.env. Nessuna lettura diretta di config() qui.
     */
    public function __construct(private readonly GoogleCalendarConfig $config) {}

    /**
     * Indica se Google Calendar è configurato (client_id e client_secret presenti). È il
     * predicato UNICO del config-gating: la UI lo usa per abilitare/disabilitare la
     * connessione, i metodi di rete per decidere se sollevare
     * {@see CalendarProviderNotConfigured}.
     *
     * Statico per i chiamanti che non hanno un'istanza (controller/provider/pagina);
     * risolve il resolver dal container, così onora l'override DB→.env come le istanze.
     */
    public static function configured(): bool
    {
        return app(GoogleCalendarConfig::class)->configured();
    }

    /**
     * Costruisce l'URL di consenso a cui reindirizzare l'utente.
     *
     * - `access_type=offline` + `prompt=consent`: forzano Google a restituire SEMPRE un
     *   refresh_token (senza `consent` Google lo omette dopo la prima autorizzazione,
     *   lasciando la connessione senza modo di rinnovarsi).
     * - `state`: token anti-CSRF verificato in callback (legato alla sessione).
     *
     * @throws CalendarProviderNotConfigured se le credenziali OAuth non sono impostate.
     */
    public function authorizationUrl(string $state): string
    {
        $this->ensureConfigured();

        $query = http_build_query([
            'client_id' => (string) $this->config->clientId(),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'access_type' => 'offline',
            'prompt' => 'consent',
            'state' => $state,
        ]);

        return self::AUTH_ENDPOINT.'?'.$query;
    }

    /**
     * Scambia l'authorization `code` (dalla callback) con i token iniziali.
     *
     * @return array{access_token: string, refresh_token: string|null, expires_in: int, scope: string|null, token_type: string|null}
     *
     * @throws CalendarProviderNotConfigured se le credenziali OAuth non sono impostate.
     */
    public function exchangeCode(string $code): array
    {
        $this->ensureConfigured();

        $response = Http::asForm()
            ->acceptJson()
            ->post(self::TOKEN_ENDPOINT, [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'client_id' => (string) $this->config->clientId(),
                'client_secret' => (string) $this->config->clientSecret(),
                'redirect_uri' => $this->redirectUri(),
            ])
            ->throw();

        /** @var array<string, mixed> $data */
        $data = $response->json();

        return $this->normalizeTokens($data);
    }

    /**
     * Rinnova l'access token dal refresh token.
     *
     * Google può NON restituire un nuovo refresh_token sul refresh: il chiamante deve
     * conservare quello esistente. Qui normalizziamo comunque il payload nella stessa
     * forma di {@see exchangeCode()} (refresh_token null quando assente).
     *
     * @return array{access_token: string, refresh_token: string|null, expires_in: int, scope: string|null, token_type: string|null}
     *
     * @throws CalendarProviderNotConfigured se le credenziali OAuth non sono impostate.
     */
    public function refresh(string $refreshToken): array
    {
        $this->ensureConfigured();

        $response = Http::asForm()
            ->acceptJson()
            ->post(self::TOKEN_ENDPOINT, [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
                'client_id' => (string) $this->config->clientId(),
                'client_secret' => (string) $this->config->clientSecret(),
            ])
            ->throw();

        /** @var array<string, mixed> $data */
        $data = $response->json();

        return $this->normalizeTokens($data);
    }

    /**
     * Normalizza il payload del token endpoint in una struttura tipizzata e stabile.
     *
     * @param  array<string, mixed>  $data
     * @return array{access_token: string, refresh_token: string|null, expires_in: int, scope: string|null, token_type: string|null}
     */
    private function normalizeTokens(array $data): array
    {
        return [
            'access_token' => is_string($data['access_token'] ?? null) ? $data['access_token'] : '',
            'refresh_token' => is_string($data['refresh_token'] ?? null) ? $data['refresh_token'] : null,
            'expires_in' => is_numeric($data['expires_in'] ?? null) ? (int) $data['expires_in'] : 0,
            'scope' => is_string($data['scope'] ?? null) ? $data['scope'] : null,
            'token_type' => is_string($data['token_type'] ?? null) ? $data['token_type'] : null,
        ];
    }

    /**
     * URI di callback OAuth. Usa quello esplicito in config (registrato in Google Cloud
     * Console) o, in fallback, la rotta nominata risolta a runtime.
     */
    private function redirectUri(): string
    {
        $configured = $this->config->redirectUri();

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        return route('calendar.google.callback');
    }

    /**
     * @throws CalendarProviderNotConfigured
     */
    private function ensureConfigured(): void
    {
        if (! $this->config->configured()) {
            throw CalendarProviderNotConfigured::google();
        }
    }
}
