<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Organization;
use App\Models\User;

/**
 * Policy per Organization.
 *
 * L'aggiornamento richiede il permesso team-scoped `organization.update`:
 * il team id (ente corrente) è impostato dal contesto tenancy (Filament), che
 * arriva nella slice 3. Fino ad allora il team id va impostato esplicitamente
 * (Action/test). Il Super Admin bypassa tutto via Gate::before.
 *
 * Il tenant scoping completo (limitare update all'ente di appartenenza) sarà
 * completato con il middleware di tenancy della slice 3.
 */
class OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Organization $organization): bool
    {
        return true;
    }

    /**
     * Registrazione self-service dell'ente: qualunque utente autenticato può
     * registrare un nuovo ente e diventarne Owner (decisione di prodotto:
     * "chi registra un ente lo gestisce subito internamente"). L'ente nasce
     * in stato `Pending`; la verifica Super Admin (piano §6) gaterà la
     * visibilità PUBBLICA del catalogo in Fase 2, non questa creazione.
     */
    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->can(PermissionName::OrganizationUpdate->value);
    }

    public function delete(User $user, Organization $organization): bool
    {
        return false;
    }
}
