<?php

declare(strict_types=1);

namespace App\Support\Site;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * URL dell'area personale usati dalla shell pubblica (header, menu mobile, footer), in un
 * solo punto.
 *
 * Accesso e registrazione dell'adottante sono le pagine pubbliche Livewire `login` (/accedi)
 * e `register` (/registrati) dalla fase UI-5 (docs/PIANO-UI-FRONTEND.md §5 UI-5): il pannello
 * Filament /account non ha più pagine di autenticazione proprie. Anche l'uscita è una route
 * pubblica (`logout`, POST /esci).
 */
final readonly class AccountLinks
{
    /** Pagina di accesso dell'adottante (/accedi). */
    public function login(): string
    {
        return route('login');
    }

    /** Registrazione dell'adottante (/registrati). */
    public function register(): string
    {
        return route('register');
    }

    /** Area adottante (/account). */
    public function account(): string
    {
        return route('filament.adopter.home');
    }

    /**
     * Uscita (POST con token CSRF): la route pubblica, non quella del pannello adottante,
     * che passerebbe dal suo authMiddleware (EnsureAdopterRole darebbe il ruolo Adopter
     * anche allo staff degli enti).
     */
    public function logout(): string
    {
        return route('logout');
    }

    /** Gestione dell'ente (/manage): Filament porta al primo ente operabile. */
    public function manage(): string
    {
        return route('filament.shelter.tenant');
    }

    /**
     * L'utente può entrare nel pannello degli enti: è membro verificato di almeno un ente
     * non sospeso. È lo stesso criterio dei tenant del pannello shelter
     * (User::getTenants() → operableOrganizations()), così il link non porta mai a un
     * pannello senza enti da scegliere.
     */
    public function canManage(?Authenticatable $user): bool
    {
        return $user instanceof User && $user->operableOrganizations()->exists();
    }
}
