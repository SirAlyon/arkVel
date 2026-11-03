<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Adoption;
use App\Models\User;

/**
 * Policy per Adoption.
 *
 * L'adozione ha DUE facce (come AdoptionApplicationPolicy):
 *  - OWNERSHIP: l'adottante proprietario vede la propria adozione (`is()`),
 *    senza permessi spatie.
 *  - TEAM-SCOPED: lo staff dell'ente vede le adozioni del proprio ente, con
 *    permesso team-scoped (riusa ApplicationView) + membership VERIFICATA
 *    (doppia difesa contro fughe cross-tenant).
 *
 * L'adozione è generata SOLO da App\Actions\Adoptions\FinalizeAdoption: non
 * esiste create/update/delete via UI, quindi qui sono negati (il Super Admin
 * resta l'unica eccezione, via Gate::before in AppServiceProvider).
 * Registrazione: auto-discovery Laravel (App\Models\X → App\Policies\XPolicy).
 */
class AdoptionPolicy
{
    /**
     * Le liste sono scoped dal chiamante (ownership adottante o tenant staff):
     * l'accesso all'elenco è permissivo, l'isolamento è a livello di query/riga.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * La vede il proprietario adottante, oppure lo staff dell'ente (con permesso
     * di vista candidature e membership verificata dell'ente proprietario).
     */
    public function view(User $user, Adoption $adoption): bool
    {
        return $user->is($adoption->user)
            || ($user->can(PermissionName::ApplicationView->value)
                && $user->isVerifiedMemberOf($adoption->organization));
    }

    /**
     * L'adozione nasce solo dalla finalizzazione (FinalizeAdoption): nessuna
     * creazione manuale dall'UI.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * L'adozione è un atto: non si modifica dall'UI.
     */
    public function update(User $user, Adoption $adoption): bool
    {
        return false;
    }

    /**
     * L'adozione non si cancella (il Super Admin resta l'unica eccezione, via
     * Gate::before).
     */
    public function delete(User $user, Adoption $adoption): bool
    {
        return false;
    }
}
