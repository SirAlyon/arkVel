<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\AdoptionApplication;
use App\Models\User;

/**
 * Policy per AdoptionApplication.
 *
 * La candidatura ha DUE facce, quindi questa policy combina i due pattern del
 * progetto:
 *  - OWNERSHIP (come AdopterProfilePolicy): l'adottante proprietario vede e
 *    ritira la propria candidatura (`is()`), senza permessi spatie.
 *  - TEAM-SCOPED (come AnimalPolicy): lo staff dell'ente gestisce le candidature
 *    del proprio ente, con permesso team-scoped + membership VERIFICATA (doppia
 *    difesa contro fughe cross-tenant).
 *
 * Il Super Admin bypassa tutto via Gate::before (AppServiceProvider).
 * Registrazione: auto-discovery Laravel (App\Models\X → App\Policies\XPolicy).
 */
class AdoptionApplicationPolicy
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
     * di vista e membership verificata).
     */
    public function view(User $user, AdoptionApplication $application): bool
    {
        return $user->is($application->user)
            || ($user->can(PermissionName::ApplicationView->value)
                && $user->isVerifiedMemberOf($application->organization));
    }

    /**
     * Chiunque può candidarsi: SubmitApplication impone profilo/consenso completo
     * e unicità animale/utente (con lo unique DB a backstop).
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Gestione staff (avanzamento/note/assegnazione): permesso di gestione +
     * membership verificata dell'ente proprietario.
     */
    public function update(User $user, AdoptionApplication $application): bool
    {
        return $user->can(PermissionName::ApplicationManage->value)
            && $user->isVerifiedMemberOf($application->organization);
    }

    /**
     * Ritiro (azione dell'adottante): solo il proprietario. Rispecchia la guardia
     * di dominio in WithdrawApplication ed è l'entry-point usato dalla UI
     * adottante (slice 8).
     */
    public function withdraw(User $user, AdoptionApplication $application): bool
    {
        return $user->is($application->user);
    }

    /**
     * Le candidature non si cancellano: si ritirano o si respingono. Nessuno
     * può eliminarle (il Super Admin resta l'unica eccezione, via Gate::before).
     */
    public function delete(User $user, AdoptionApplication $application): bool
    {
        return false;
    }
}
