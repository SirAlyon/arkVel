<?php

declare(strict_types=1);

namespace App\Support\Auth;

use Illuminate\Validation\Rules\Password;

/**
 * Valori delle regole sulle password mostrati nei testi (aiuto del campo, mail, conferma
 * della richiesta di reset), letti dalla configurazione invece di essere scritti a mano:
 * cambiando `Password::defaults()` o `config/auth.php` i testi seguono.
 */
final class PasswordPolicy
{
    /** Lunghezza minima di `Password::defaults()` (8 se non configurata). */
    public static function minLength(): int
    {
        $min = Password::default()->appliedRules()['min'];

        return is_int($min) ? $min : 8;
    }

    /** Minuti di validità del link per reimpostare la password (broker di default). */
    public static function resetLinkExpiryMinutes(): int
    {
        $broker = config('auth.defaults.passwords');

        return (int) config('auth.passwords.'.(is_string($broker) ? $broker : 'users').'.expire', 60);
    }

    /** Minuti di validità del link firmato di verifica dell'email. */
    public static function verificationLinkExpiryMinutes(): int
    {
        return (int) config('auth.verification.expire', 60);
    }
}
