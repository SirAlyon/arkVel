<?php

declare(strict_types=1);

namespace App\Support\Site;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Route;

/**
 * URL dell'area personale usati dalla shell pubblica (header, menu mobile, footer), in un
 * solo punto.
 *
 * Oggi accesso e registrazione dell'adottante sono le pagine del pannello Filament `/account`
 * (AdopterPanelProvider). In UI-5 diventano le route pubbliche Livewire `login` e `register`
 * (docs/PIANO-UI-FRONTEND.md §5 UI-5): basterà cambiare login() e register() qui, senza
 * toccare le viste. L'uscita è già una route pubblica (`logout.public`).
 */
final readonly class AccountLinks
{
    /** Pagina di accesso dell'adottante. UI-5: route('login'). */
    public function login(): string
    {
        return route('filament.adopter.auth.login');
    }

    /** Registrazione dell'adottante, se il pannello la offre. UI-5: route('register'). */
    public function register(): ?string
    {
        return Route::has('filament.adopter.auth.register')
            ? route('filament.adopter.auth.register')
            : null;
    }

    /** Area adottante (/account). */
    public function account(): string
    {
        return route('filament.adopter.home');
    }

    /**
     * Uscita (POST con token CSRF): la route pubblica, non quella del pannello adottante,
     * che passerebbe dal suo authMiddleware (EnsureAdopterRole darebbe il ruolo Adopter
     * anche allo staff degli enti) e poi rimanderebbe al login di Filament.
     * UI-5: route('logout').
     */
    public function logout(): string
    {
        return route('logout.public');
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
