<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Dà all'utente il ruolo di piattaforma `Adopter`, TEAM-INDEPENDENT, se non ce l'ha già.
 *
 * Perché team-independent: `Adopter` è un ruolo di PIATTAFORMA / self-role, non
 * org-scoped (cfr. RoleName::isOrganizationScoped() === false). Esattamente come
 * per il Super Admin (vedi User::isSuperAdmin()), in modalità teams la scope
 * spatie `$this->roles()` filtra su `wherePivot('team_id', getPermissionsTeamId())`:
 * se leggessimo il ruolo sotto un team id qualsiasi, l'assegnazione verrebbe
 * mascherata ogni volta che il team corrente non coincide. Il RILEVAMENTO qui è
 * quindi team-independent: query diretta sul pivot (identica a isSuperAdmin()).
 *
 * L'ASSEGNAZIONE usa il team sentinel di piattaforma (RoleName::PLATFORM_TEAM_ID,
 * ossia 0): la colonna model_has_roles.team_id è NOT NULL (parte della PK), quindi
 * non esiste un'assegnazione "senza team". Il valore è però irrilevante perché il
 * rilevamento ignora il team. È la stessa convenzione del Super Admin nel
 * DemoDataSeeder: un unico sentinel neutro (0), che non coincide con alcun ente
 * reale (le PK organizations partono da 1). Il team id precedente viene ripristinato
 * per non lasciare residui a valle.
 *
 * Il ruolo `Adopter` NON porta permessi (RolesAndPermissionsSeeder lo sincronizza
 * a vuoto): è bookkeeping/segmentazione, l'autorizzazione è per OWNERSHIP
 * (AdopterProfilePolicy). Idempotente: con il ruolo già presente è un no-op.
 *
 * Usata dal middleware EnsureAdopterRole (ogni accesso al pannello /account) e dalla
 * registrazione pubblica dell'adottante (RegisterAdopter).
 */
final readonly class AssignAdopterRole
{
    public function __construct(private PermissionRegistrar $permissionRegistrar) {}

    public function handle(User $user): void
    {
        if ($this->hasAdopterRole($user)) {
            return;
        }

        $previousTeamId = $this->permissionRegistrar->getPermissionsTeamId();
        $this->permissionRegistrar->setPermissionsTeamId(RoleName::PLATFORM_TEAM_ID);

        try {
            $user->assignRole(RoleName::Adopter->value);
        } finally {
            $this->permissionRegistrar->setPermissionsTeamId($previousTeamId);
        }
    }

    /**
     * Rileva il ruolo `Adopter` con una query diretta sul pivot, TEAM-INDEPENDENT:
     * identica strategia di User::isSuperAdmin(). Unica fonte di verità.
     */
    private function hasAdopterRole(User $user): bool
    {
        /** @var array<string, string> $tables */
        $tables = config('permission.table_names');
        /** @var array<string, string> $columns */
        $columns = config('permission.column_names');

        $modelHasRoles = $tables['model_has_roles'];
        $rolesTable = $tables['roles'];
        $morphKey = $columns['model_morph_key'];
        $pivotRoleKey = $columns['role_pivot_key'] ?? 'role_id';

        return DB::table($modelHasRoles)
            ->join(
                $rolesTable,
                "{$modelHasRoles}.{$pivotRoleKey}",
                '=',
                "{$rolesTable}.id",
            )
            ->where("{$modelHasRoles}.{$morphKey}", $user->getKey())
            ->where("{$modelHasRoles}.model_type", $user->getMorphClass())
            ->where("{$rolesTable}.name", RoleName::Adopter->value)
            ->where("{$rolesTable}.guard_name", config('auth.defaults.guard'))
            ->exists();
    }
}
