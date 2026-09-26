<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\Animal;
use App\Models\User;

/**
 * Policy per Animal.
 *
 * Doppia difesa: oltre allo scoping delle query per tenant (Filament tenancy),
 * le azioni puntuali verificano che l'utente sia membro VERIFICATO dell'ente
 * proprietario dell'animale, cosi da escludere fughe cross-tenant anche se una
 * query dimenticasse lo scope.
 *
 * I permessi sono team-scoped (spatie/permission in modalita teams): il team id
 * (ente corrente) e impostato dal contesto tenancy o esplicitamente nei test.
 * Il Super Admin bypassa tutto via Gate::before (AppServiceProvider).
 */
class AnimalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::AnimalView->value);
    }

    public function view(User $user, Animal $animal): bool
    {
        return $user->can(PermissionName::AnimalView->value)
            && $user->isVerifiedMemberOf($animal->organization);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::AnimalCreate->value);
    }

    public function update(User $user, Animal $animal): bool
    {
        return $user->can(PermissionName::AnimalUpdate->value)
            && $user->isVerifiedMemberOf($animal->organization);
    }

    public function delete(User $user, Animal $animal): bool
    {
        return $user->can(PermissionName::AnimalDelete->value)
            && $user->isVerifiedMemberOf($animal->organization);
    }

    /**
     * Chi puo' cancellare (soft delete) puo' anche ripristinare nel proprio ente.
     * Serve alle azioni di restore delle Resource Filament (slice 5).
     */
    public function restore(User $user, Animal $animal): bool
    {
        return $user->can(PermissionName::AnimalDelete->value)
            && $user->isVerifiedMemberOf($animal->organization);
    }

    /**
     * Eliminazione definitiva: stesso permesso del soft delete, ristretta al
     * proprio ente. Il Super Admin passa da Gate::before.
     */
    public function forceDelete(User $user, Animal $animal): bool
    {
        return $user->can(PermissionName::AnimalDelete->value)
            && $user->isVerifiedMemberOf($animal->organization);
    }
}
