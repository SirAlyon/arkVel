<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

/**
 * Allinea il team corrente di spatie/permission al tenant Filament.
 *
 * Punto d'integrazione fra la tenancy Filament e i permessi in modalità teams:
 * quando Filament ha risolto il tenant corrente (Organization), impostiamo il
 * suo id come team id sul registrar. Così ogni check `can()`/`hasRole()`
 * eseguito nel pannello è scoped al tenant attivo (niente fughe cross-tenant).
 *
 * Registrato come tenant middleware persistente sul pannello shelter: gira sia
 * sul boot della sessione tenant sia su ogni request Livewire successiva.
 */
class SetPermissionsTeamFromTenant
{
    public function __construct(
        private readonly PermissionRegistrar $permissionRegistrar,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = Filament::getTenant();

        if ($tenant instanceof Organization) {
            $this->permissionRegistrar->setPermissionsTeamId($tenant->getKey());
        }

        return $next($request);
    }
}
