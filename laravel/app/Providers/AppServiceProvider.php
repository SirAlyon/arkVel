<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->registerSuperAdminBypass();
    }

    /**
     * Il Super Admin è un ruolo di piattaforma e bypassa ogni check.
     *
     * In modalità teams le assegnazioni in model_has_roles portano sempre un
     * team id (colonna NOT NULL, parte della PK), quindi non è possibile
     * un'assegnazione "senza team". Per un ruolo di piattaforma il team è
     * irrilevante: rileviamo il Super Admin con una query diretta sul pivot,
     * ignorando il team corrente, così il bypass funziona in ogni contesto.
     */
    private function registerSuperAdminBypass(): void
    {
        Gate::before(function (User $user): ?bool {
            return $this->isSuperAdmin($user) ? true : null;
        });
    }

    private function isSuperAdmin(User $user): bool
    {
        $tables = config('permission.table_names');
        $columns = config('permission.column_names');

        return DB::table($tables['model_has_roles'])
            ->join(
                $tables['roles'],
                "{$tables['model_has_roles']}.".($columns['role_pivot_key'] ?? 'role_id'),
                '=',
                "{$tables['roles']}.id",
            )
            ->where("{$tables['model_has_roles']}.{$columns['model_morph_key']}", $user->getKey())
            ->where("{$tables['model_has_roles']}.model_type", $user->getMorphClass())
            ->where("{$tables['roles']}.name", RoleName::SuperAdmin->value)
            ->where("{$tables['roles']}.guard_name", config('auth.defaults.guard'))
            ->exists();
    }
}
