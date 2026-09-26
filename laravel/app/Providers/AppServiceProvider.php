<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
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
     * Il rilevamento del Super Admin è TEAM-INDEPENDENT e vive in un solo posto,
     * {@see User::isSuperAdmin()} (unica fonte di verità): il bypass qui si limita
     * a delegarvi. Ritorna `null` (non `false`) quando NON è Super Admin, così da
     * NON cortocircuitare le altre policy — `Gate::before` interrompe la catena
     * solo con un valore non-null.
     */
    private function registerSuperAdminBypass(): void
    {
        Gate::before(function (User $user): ?bool {
            return $user->isSuperAdmin() ? true : null;
        });
    }
}
