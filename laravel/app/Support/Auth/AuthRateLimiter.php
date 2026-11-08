<?php

declare(strict_types=1);

namespace App\Support\Auth;

use Illuminate\Cache\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Rate limit dei flussi di autenticazione pubblici (accesso, registrazione, reset e
 * reinvio della verifica), sopra il RateLimiter di Laravel (store di cache).
 *
 * Le chiavi sono hash (`key()`): email e IP non finiscono in chiaro nella cache.
 * Al superamento del limite l'errore è una ValidationException sul campo indicato, con
 * un messaggio tradotto che dice quanto attendere (secondi o minuti): la pagina lo mostra come gli altri
 * errori del modulo (riepilogo + campo).
 */
final readonly class AuthRateLimiter
{
    public function __construct(private RateLimiter $limiter) {}

    /**
     * Chiave del limitatore: prefisso del flusso + hash delle parti (email, IP, id utente).
     */
    public static function key(string $flow, string ...$parts): string
    {
        return 'auth-public:'.$flow.':'.sha1(implode('|', $parts));
    }

    /**
     * @throws ValidationException se i tentativi sono finiti
     */
    public function ensureAvailable(string $key, int $maxAttempts, string $field): void
    {
        if (! $this->limiter->tooManyAttempts($key, $maxAttempts)) {
            return;
        }

        throw ValidationException::withMessages([$field => $this->throttledMessage($key)]);
    }

    public function hit(string $key, int $decaySeconds): void
    {
        $this->limiter->hit($key, $decaySeconds);
    }

    public function clear(string $key): void
    {
        $this->limiter->clear($key);
    }

    /**
     * Attesa in secondi fino a un minuto, poi in minuti arrotondati per eccesso (con il
     * plurale giusto in ogni lingua).
     */
    public function throttledMessage(string $key): string
    {
        $seconds = max(1, $this->limiter->availableIn($key));

        if ($seconds <= 60) {
            return trans_choice('auth_public.throttled.seconds', $seconds, ['count' => $seconds]);
        }

        $minutes = (int) ceil($seconds / 60);

        return trans_choice('auth_public.throttled.minutes', $minutes, ['count' => $minutes]);
    }
}
