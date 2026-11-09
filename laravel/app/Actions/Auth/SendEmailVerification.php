<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use App\Support\Auth\AuthRateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Rimanda la mail di verifica dell'indirizzo (bottone "Invia di nuovo" di /verifica-email).
 *
 * Come il pannello Filament che sostituisce: niente invio se l'indirizzo è già verificato,
 * al massimo MAX_ATTEMPTS invii per utente in DECAY_SECONDS (la route ha in più il
 * throttle 6 al minuto). La mail è quella di User::sendEmailVerificationNotification().
 */
final readonly class SendEmailVerification
{
    public const MAX_ATTEMPTS = 2;

    public const DECAY_SECONDS = 60;

    public function __construct(private AuthRateLimiter $rateLimiter) {}

    /**
     * @return bool true se la mail è partita, false se l'indirizzo era già verificato
     *
     * @throws ValidationException troppi invii ravvicinati (campo `verification`)
     */
    public function handle(User $user): bool
    {
        if ($user->hasVerifiedEmail()) {
            return false;
        }

        $key = AuthRateLimiter::key('verification', (string) $user->getKey());

        $this->rateLimiter->ensureAvailable($key, self::MAX_ATTEMPTS, 'verification');
        $this->rateLimiter->hit($key, self::DECAY_SECONDS);

        $user->sendEmailVerificationNotification();

        return true;
    }
}
