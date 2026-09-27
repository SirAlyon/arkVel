<?php

declare(strict_types=1);

namespace App\Support\Calendar\Google;

use App\Models\CalendarConnection;
use App\Support\Calendar\Providers\GoogleCalendarProvider;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Fornisce al {@see GoogleCalendarProvider} un access
 * token VALIDO per una connessione, rinnovandolo in modo trasparente quando è scaduto.
 *
 * I token vivono nel campo cifrato `credentials` della {@see CalendarConnection}
 * (access_token, refresh_token, expires_at). Se l'access token manca o è scaduto (con un
 * piccolo margine di sicurezza per l'orologio/latenza), chiama {@see GoogleOAuth::refresh()},
 * PERSISTE il nuovo access token + scadenza (preservando il refresh_token, che Google
 * può non restituire) e restituisce il token fresco.
 *
 * Se non c'è un refresh_token (connessione mai autorizzata offline o revocata) solleva:
 * la connessione va ri-collegata. La transizione a status=Error è gestita dal try/catch
 * del job di push — qui il compito è solo produrre un token o fallire in modo chiaro.
 */
final class GoogleTokenManager
{
    /**
     * Margine (secondi) sottratto alla scadenza: rinnoviamo un filo PRIMA per evitare di
     * usare un token che scade durante la richiesta.
     */
    private const int EXPIRY_SKEW_SECONDS = 60;

    public function __construct(
        private readonly GoogleOAuth $oauth,
    ) {}

    /**
     * Restituisce un access token valido per la connessione, rinnovandolo e
     * persistendolo se necessario.
     *
     * @throws RuntimeException se non c'è un refresh token con cui rinnovare.
     */
    public function accessTokenFor(CalendarConnection $connection): string
    {
        $credentials = $connection->credentials ?? [];

        $accessToken = is_string($credentials['access_token'] ?? null) ? $credentials['access_token'] : null;

        if ($accessToken !== null && $accessToken !== '' && ! $this->isExpired($credentials)) {
            return $accessToken;
        }

        return $this->refreshAndPersist($connection, $credentials);
    }

    /**
     * Rinnova l'access token dal refresh token e lo persiste (cifrato) sulla connessione.
     *
     * @param  array<string, mixed>  $credentials
     *
     * @throws RuntimeException se manca il refresh token.
     */
    private function refreshAndPersist(CalendarConnection $connection, array $credentials): string
    {
        $refreshToken = is_string($credentials['refresh_token'] ?? null) ? $credentials['refresh_token'] : null;

        if ($refreshToken === null || $refreshToken === '') {
            throw new RuntimeException('Google Calendar connection is missing a refresh token; reconnect required.');
        }

        $tokens = $this->oauth->refresh($refreshToken);

        // Google può NON restituire un nuovo refresh_token sul refresh: conserviamo
        // quello esistente per non perdere la capacità di rinnovo futura.
        $newRefreshToken = $tokens['refresh_token'] ?? $refreshToken;

        $connection->forceFill([
            'credentials' => [
                'access_token' => $tokens['access_token'],
                'refresh_token' => $newRefreshToken,
                'expires_at' => $this->expiresAt($tokens['expires_in'])->toIso8601String(),
                'scope' => $tokens['scope'] ?? ($credentials['scope'] ?? null),
                'token_type' => $tokens['token_type'] ?? ($credentials['token_type'] ?? null),
            ],
        ])->save();

        return $tokens['access_token'];
    }

    /**
     * Il token è scaduto (o manca la scadenza)? Applica lo skew per rinnovare in anticipo.
     *
     * @param  array<string, mixed>  $credentials
     */
    private function isExpired(array $credentials): bool
    {
        $expiresAt = $credentials['expires_at'] ?? null;

        if (! is_string($expiresAt) || $expiresAt === '') {
            // Senza una scadenza nota non possiamo fidarci del token: forziamo il refresh.
            return true;
        }

        return Carbon::parse($expiresAt)->subSeconds(self::EXPIRY_SKEW_SECONDS)->isPast();
    }

    /**
     * Calcola l'istante di scadenza dall'`expires_in` (secondi) restituito da Google.
     */
    private function expiresAt(int $expiresIn): Carbon
    {
        return Carbon::now()->addSeconds(max($expiresIn, 0));
    }
}
