<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\CalendarConnection;
use App\Models\User;

/**
 * Policy per CalendarConnection.
 *
 * Autorizzazione TEAM-SCOPED coerente con le altre risorse d'ente: gestire le
 * connessioni di calendario richiede il permesso {@see PermissionName::CalendarManage}
 * (mappato a OrgOwner/OrgStaff, NON al volontario) E la membership VERIFICATA dell'ente
 * proprietario della connessione — doppia difesa contro fughe cross-tenant. Il
 * volontario (sola vista sugli appuntamenti) è respinto ovunque.
 *
 * Le liste (viewAny) sono comunque scoped dalla query della pagina al tenant corrente;
 * qui viewAny gate la sola CAPACITÀ (permesso), l'isolamento è a livello di query.
 *
 * Il Super Admin bypassa tutto via Gate::before (AppServiceProvider).
 * Registrazione: auto-discovery Laravel (App\Models\X → App\Policies\XPolicy).
 */
class CalendarConnectionPolicy
{
    /**
     * Può vedere l'elenco delle connessioni chi ha la capacità di gestione. Lo scoping
     * al tenant corrente è nella query della pagina.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::CalendarManage->value);
    }

    /**
     * Può vedere la singola connessione lo staff dell'ente (capacità + membership
     * verificata dell'ente proprietario).
     */
    public function view(User $user, CalendarConnection $connection): bool
    {
        return $this->manages($user, $connection);
    }

    /**
     * Creare una connessione richiede la capacità di gestione. L'appartenenza all'ente
     * di destinazione è garantita dal contesto tenant della pagina (create lato UI usa
     * sempre il tenant corrente).
     */
    public function create(User $user): bool
    {
        return $user->can(PermissionName::CalendarManage->value);
    }

    /**
     * Modificare/riconnettere: capacità + membership verificata dell'ente proprietario.
     */
    public function update(User $user, CalendarConnection $connection): bool
    {
        return $this->manages($user, $connection);
    }

    /**
     * Disconnettere (eliminare): capacità + membership verificata dell'ente proprietario.
     */
    public function delete(User $user, CalendarConnection $connection): bool
    {
        return $this->manages($user, $connection);
    }

    /**
     * Predicato condiviso: l'utente può gestire questa connessione se ha il permesso
     * CalendarManage ED è membro VERIFICATO dell'ente proprietario. Nessuna fuga
     * cross-tenant: il permesso da solo non basta, serve l'appartenenza all'ente.
     */
    private function manages(User $user, CalendarConnection $connection): bool
    {
        return $user->can(PermissionName::CalendarManage->value)
            && $user->isVerifiedMemberOf($connection->organization);
    }
}
