<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\RoleName;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garantisce che ogni utente autenticato del pannello adopter abbia il ruolo
 * `Adopter`, assegnandolo TEAM-INDEPENDENT (team id null) se manca.
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
 * reale (le PK organizations partono da 1).
 *
 * Il ruolo `Adopter` NON porta permessi (RolesAndPermissionsSeeder lo sincronizza
 * a vuoto): l'autorizzazione in questo pannello è per OWNERSHIP dell'utente
 * (AdopterProfilePolicy). Il ruolo serve solo da bookkeeping/segmentazione.
 *
 * Idempotente ed economico: il rilevamento team-independent evita doppie
 * assegnazioni, poi l'assegnazione avviene una sola volta. Registrato
 * nell'authMiddleware del pannello, gira per gli utenti autenticati su ogni
 * request Livewire. Ripristina il team id precedente per non lasciare residui.
 */
class EnsureAdopterRole
{
    public function __construct(
        private readonly PermissionRegistrar $permissionRegistrar,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Legge l'utente dal guard autenticato (impostato dal middleware
        // Authenticate che precede questo nell'authMiddleware): coerente con
        // l'uso di Auth::user() altrove (RegisterOrganization, la Page adopter).
        $user = Auth::user();

        if ($user instanceof User && ! $this->hasAdopterRole($user)) {
            // Assegnazione sotto il team sentinel di piattaforma (il team è
            // irrilevante: il rilevamento lo ignora). Ripristina il team id
            // precedente per non contaminare eventuali contesti a valle.
            $previousTeamId = $this->permissionRegistrar->getPermissionsTeamId();
            $this->permissionRegistrar->setPermissionsTeamId(RoleName::PLATFORM_TEAM_ID);

            try {
                $user->assignRole(RoleName::Adopter->value);
            } finally {
                $this->permissionRegistrar->setPermissionsTeamId($previousTeamId);
            }
        }

        return $next($request);
    }

    /**
     * Rileva il ruolo `Adopter` con una query diretta sul pivot, TEAM-INDEPENDENT:
     * identica strategia di User::isSuperAdmin(). Evita la scope spatie roles()
     * (team-scoped) che maschererebbe l'assegnazione quando il team corrente non
     * coincide con quello usato in fase di scrittura. Unica fonte di verità.
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
