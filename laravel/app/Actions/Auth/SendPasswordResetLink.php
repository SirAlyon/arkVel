<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Data\Auth\ForgotPasswordData;
use App\Exceptions\AlreadyAuthenticated;
use App\Support\Auth\AuthRateLimiter;
use App\Support\Auth\GuestOnly;
use Illuminate\Contracts\Auth\PasswordBrokerFactory;
use Illuminate\Validation\ValidationException;

/**
 * Richiesta del link per reimpostare la password (/password-dimenticata).
 *
 * Il password broker di Laravel crea il token (tabella password_reset_tokens, scadenza
 * e throttle per utente da config/auth.php) e chiama User::sendPasswordResetNotification()
 * (mail in coda con il link alla route pubblica `password.reset`), poi emette
 * PasswordResetLinkSent.
 *
 * Non rivela mai se l'email è registrata: l'esito del broker (inviato, utente
 * inesistente, già richiesto da poco) viene ignorato e la pagina mostra sempre lo stesso
 * messaggio. A differenza del pannello Filament, che diceva "utente non trovato".
 *
 * Rate limit: MAX_ATTEMPTS richieste per IP in DECAY_SECONDS, oltre al throttle per
 * utente del broker. L'errore di throttle non dipende dall'email, quindi non rivela nulla.
 *
 * Solo ospiti ({@see GuestOnly}): il middleware `guest` non vale sulle richieste Livewire.
 */
final readonly class SendPasswordResetLink
{
    public const MAX_ATTEMPTS = 5;

    public const DECAY_SECONDS = 600;

    public function __construct(
        private PasswordBrokerFactory $passwords,
        private GuestOnly $guestOnly,
        private AuthRateLimiter $rateLimiter,
    ) {}

    /**
     * @throws AlreadyAuthenticated la sessione è già autenticata
     * @throws ValidationException troppe richieste dallo stesso IP (campo `email`)
     */
    public function handle(ForgotPasswordData $data, string $ip): void
    {
        $this->guestOnly->ensure();

        $key = AuthRateLimiter::key('password-request', $ip);

        $this->rateLimiter->ensureAvailable($key, self::MAX_ATTEMPTS, 'email');
        $this->rateLimiter->hit($key, self::DECAY_SECONDS);

        $this->passwords->broker()->sendResetLink(['email' => $data->email]);
    }
}
