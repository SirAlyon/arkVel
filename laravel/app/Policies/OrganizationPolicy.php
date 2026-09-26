<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;

/**
 * Policy provvisoria per Organization.
 *
 * In questa slice la logica è difensiva: lettura consentita agli utenti
 * autenticati, scrittura negata di default. I permessi team-scoped
 * (spatie/laravel-permission in modalità teams) e il tenant scoping
 * saranno introdotti nella slice 2, quando esisteranno la membership
 * (pivot organization_user) e i ruoli.
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
        return false;
    }

    public function delete(User $user, Organization $organization): bool
    {
        return false;
    }
}
