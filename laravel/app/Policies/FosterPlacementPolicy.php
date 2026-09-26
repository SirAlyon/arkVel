<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\FosterPlacement;
use App\Models\User;

/**
 * Policy per FosterPlacement (affido fisico).
 *
 * Rispecchia FosterApplicationPolicy: l'affido ha DUE facce, quindi combina i
 * due pattern del progetto:
 *  - OWNERSHIP: l'affidatario proprietario vede il proprio affido (`is()`),
 *    senza permessi spatie.
 *  - TEAM-SCOPED: lo staff dell'ente gestisce gli affidi del proprio ente, con
 *    permesso team-scoped + membership VERIFICATA (doppia difesa contro fughe
 *    cross-tenant).
 *
 * Riusa i permessi foster della slice 1 (FosterApplicationView / Manage): non
 * introduce nuovi permessi. Il Super Admin bypassa tutto via Gate::before
 * (AppServiceProvider). Registrazione: auto-discovery Laravel
 * (App\Models\X → App\Policies\XPolicy).
 */
class FosterPlacementPolicy
{
    /**
     * Le liste sono scoped dal chiamante (ownership affidatario o tenant staff):
     * l'accesso all'elenco è permissivo, l'isolamento è a livello di query/riga.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Lo vede l'affidatario proprietario, oppure lo staff dell'ente (con permesso
     * di vista e membership verificata).
     */
    public function view(User $user, FosterPlacement $fosterPlacement): bool
    {
        return $user->is($fosterPlacement->user)
            || ($user->can(PermissionName::FosterApplicationView->value)
                && $user->isVerifiedMemberOf($fosterPlacement->organization));
    }

    /**
     * Solo lo staff con permesso di gestione crea affidi (l'orchestrazione — dalla
     * pratica approvata — vive nell'Action della slice 3).
     */
    public function create(User $user): bool
    {
        return $user->can(PermissionName::FosterApplicationManage->value);
    }

    /**
     * Gestione staff (chiusura/note): permesso di gestione + membership verificata
     * dell'ente proprietario.
     */
    public function update(User $user, FosterPlacement $fosterPlacement): bool
    {
        return $user->can(PermissionName::FosterApplicationManage->value)
            && $user->isVerifiedMemberOf($fosterPlacement->organization);
    }

    /**
     * Gli affidi sono record storici: si concludono, non si cancellano. Nessuno
     * può eliminarli (il Super Admin resta l'unica eccezione, via Gate::before).
     */
    public function delete(User $user, FosterPlacement $fosterPlacement): bool
    {
        return false;
    }
}
