<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Enums\MembershipRole;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Aggiunge (o aggiorna) un utente come membro di un ente e gli assegna il
 * ruolo spatie team-scoped corrispondente.
 *
 * Il ruolo viene assegnato nel contesto del team = Organization: impostiamo
 * esplicitamente il team id sul registrar prima di assignRole, così
 * l'assegnazione (model_has_roles) porta il team id dell'ente.
 *
 * NB: i model Eloquent (Organization, User) si passano come argomenti
 * espliciti, non tramite un DTO laravel-data. Spatie\LaravelData\Data è
 * pensato per payload scalari/nested-DTO provenienti da form/request e si
 * rompe (toArray/validation/resolution) con istanze Eloquent live. Il DTO
 * tipizzato verrà usato genuinamente in Fase 2 per i payload dei form
 * (es. submit candidatura).
 */
class AddOrganizationMember
{
    public function __construct(
        private readonly PermissionRegistrar $permissionRegistrar,
    ) {}

    public function handle(
        Organization $organization,
        User $user,
        MembershipRole $role,
        bool $verified = false,
    ): OrganizationUser {
        return DB::transaction(function () use ($organization, $user, $role, $verified): OrganizationUser {
            $attributes = [
                'role' => $role->value,
                'verified' => $verified,
            ];

            $isMember = $organization->users()
                ->wherePivot('user_id', $user->getKey())
                ->exists();

            // Upsert idempotente: aggiorna la pivot esistente, oppure la crea
            // impostando anche invited_at solo al primo inserimento.
            if ($isMember) {
                $organization->users()->updateExistingPivot($user->getKey(), $attributes);
            } else {
                $organization->users()->attach($user->getKey(), [
                    ...$attributes,
                    'invited_at' => now(),
                ]);
            }

            // L'assegnazione del ruolo sta DENTRO la transazione: se fallisce,
            // il rollback annulla anche la pivot, evitando membership orfane
            // senza ruolo (stato di autorizzazione incoerente).
            $this->assignTeamScopedRole($organization, $user, $role);

            /** @var OrganizationUser $pivot */
            $pivot = $organization->users()
                ->wherePivot('user_id', $user->getKey())
                ->firstOrFail()
                ->getRelationValue('pivot');

            return $pivot;
        });
    }

    /**
     * Assegna il ruolo spatie all'utente nel contesto dell'ente (team).
     */
    private function assignTeamScopedRole(Organization $organization, User $user, MembershipRole $role): void
    {
        $previousTeamId = $this->permissionRegistrar->getPermissionsTeamId();

        $this->permissionRegistrar->setPermissionsTeamId($organization->getKey());

        try {
            $roleName = $role->toRoleName()->value;

            if (! $user->hasRole($roleName)) {
                $user->assignRole($roleName);
            }
        } finally {
            $this->permissionRegistrar->setPermissionsTeamId($previousTeamId);
        }
    }
}
