<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Data\Auth\NormalizesEmail;
use App\Models\User;

/**
 * Forma canonica di un indirizzo email: senza spazi ai lati e in minuscolo.
 *
 * Su PostgreSQL il confronto delle stringhe distingue maiuscole e minuscole: senza una
 * forma unica `Mario@Example.com` e `mario@example.com` sarebbero due account diversi e
 * l'accesso o il reset fallirebbero per una maiuscola. La usano i DTO dei moduli di
 * autenticazione ({@see NormalizesEmail}) e il mutator di
 * {@see User::email()}, che copre anche gli account creati altrove (pannelli
 * Filament, seeder, import).
 */
final class EmailAddress
{
    public static function normalize(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
