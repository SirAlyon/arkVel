<?php

declare(strict_types=1);

namespace App\Data\Auth;

/**
 * Per i DTO dei moduli di autenticazione: l'email arriva senza spazi ai lati e in
 * minuscolo PRIMA della validazione (prepareForPipeline di laravel-data gira prima delle
 * regole). Su PostgreSQL il confronto delle stringhe distingue maiuscole e minuscole:
 * senza la normalizzazione `Mario@Example.com` e `mario@example.com` sarebbero due account
 * diversi, e l'accesso o il reset fallirebbero per una maiuscola. Livewire salta il
 * middleware TrimStrings, quindi il trim serve anche qui.
 */
trait NormalizesEmail
{
    /**
     * @param  array<array-key, mixed>  $properties
     * @return array<array-key, mixed>
     */
    public static function prepareForPipeline(array $properties): array
    {
        if (isset($properties['email']) && is_string($properties['email'])) {
            $properties['email'] = self::normalizeEmail($properties['email']);
        }

        return $properties;
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
