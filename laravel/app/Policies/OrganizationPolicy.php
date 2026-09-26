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

    public function create(User $user): bool
    {
        return false;
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
