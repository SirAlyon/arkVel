<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\FosterApplication;
use App\Models\User;

/**
 * Policy per FosterApplication.
 *
 * Rispecchia AdoptionApplicationPolicy: la richiesta di affido ha DUE facce,
 * quindi questa policy combina i due pattern del progetto:
 *  - OWNERSHIP (come AdopterProfilePolicy): l'affidatario proprietario vede e
 *    ritira la propria richiesta (`is()`), senza permessi spatie.
 *  - TEAM-SCOPED (come AnimalPolicy): lo staff dell'ente gestisce le richieste
 *    del proprio ente, con permesso team-scoped + membership VERIFICATA (doppia
 *    difesa contro fughe cross-tenant).
 *
 * Il Super Admin bypassa tutto via Gate::before (AppServiceProvider).
 * Registrazione: auto-discovery Laravel (App\Models\X → App\Policies\XPolicy).
 */
class FosterApplicationPolicy
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
     * La vede il proprietario affidatario, oppure lo staff dell'ente (con permesso
     * di vista e membership verificata).
     */
    public function view(User $user, FosterApplication $fosterApplication): bool
    {
        return $user->is($fosterApplication->user)
            || ($user->can(PermissionName::FosterApplicationView->value)
                && $user->isVerifiedMemberOf($fosterApplication->organization));
    }

    /**
     * Chiunque può fare richiesta: le guardie di dominio (profilo/unicità) vivono
     * nelle Action delle slice successive, con lo unique DB a backstop.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Gestione staff (avanzamento/note/assegnazione): permesso di gestione +
     * membership verificata dell'ente proprietario.
     */
    public function update(User $user, FosterApplication $fosterApplication): bool
    {
        return $user->can(PermissionName::FosterApplicationManage->value)
            && $user->isVerifiedMemberOf($fosterApplication->organization);
    }

    /**
     * Ritiro (azione dell'affidatario): solo il proprietario.
     */
    public function withdraw(User $user, FosterApplication $fosterApplication): bool
    {
        return $user->is($fosterApplication->user);
    }

    /**
     * Le richieste di affido non si cancellano: si ritirano o si respingono.
     * Nessuno può eliminarle (il Super Admin resta l'unica eccezione, via
     * Gate::before).
     */
    public function delete(User $user, FosterApplication $fosterApplication): bool
    {
        return false;
    }
}
