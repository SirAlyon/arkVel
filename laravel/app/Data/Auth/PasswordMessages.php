<?php

declare(strict_types=1);

namespace App\Data\Auth;

/**
 * Messaggi tradotti delle regole sulla nuova password (registrazione e reimpostazione):
 * obbligatoria, conferma, e le regole di `Password::defaults()` (lunghezza minima e, se
 * configurate, maiuscole/minuscole, numeri, simboli, password compromesse).
 */
final class PasswordMessages
{
    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return [
            'password.required' => __('auth_public.validation.password_required'),
            'password.confirmed' => __('auth_public.validation.password_confirmed'),
            'password.min' => __('auth_public.validation.password_min'),
            'password.password.mixed' => __('auth_public.validation.password_mixed'),
            'password.password.letters' => __('auth_public.validation.password_letters'),
            'password.password.numbers' => __('auth_public.validation.password_numbers'),
            'password.password.symbols' => __('auth_public.validation.password_symbols'),
            'password.password.uncompromised' => __('auth_public.validation.password_uncompromised'),
        ];
    }
}
