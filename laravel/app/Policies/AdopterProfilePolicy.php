<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AdopterProfile;
use App\Models\User;

/**
 * Policy per AdopterProfile.
 *
 * A differenza delle risorse d'ente (Animal, MedicalRecord), il profilo NON è
 * tenant-scoped: l'ownership è per UTENTE. L'adottante gestisce esclusivamente
 * il proprio profilo; il confronto usa `is()` (stessa istanza/chiave), non i
 * permessi team-scoped di spatie. Il Super Admin bypassa comunque tutto via
 * Gate::before (AppServiceProvider).
 *
 * Registrazione: auto-discovery di Laravel (App\Models\X → App\Policies\XPolicy),
 * coerente con le altre policy del progetto — nessuna mappatura esplicita.
 */
class AdopterProfilePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AdopterProfile $adopterProfile): bool
    {
        return $user->is($adopterProfile->user);
    }

    /**
     * Chiunque può creare il proprio profilo: il vincolo unique su user_id
     * impedisce i duplicati a livello di database.
     */
    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, AdopterProfile $adopterProfile): bool
    {
        return $user->is($adopterProfile->user);
    }

    public function delete(User $user, AdopterProfile $adopterProfile): bool
    {
        return $user->is($adopterProfile->user);
    }
}
