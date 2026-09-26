<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PermissionName;
use App\Enums\RoleName;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Semina permessi e ruoli GLOBALI (team_id null) della Fase 1.
 *
 * I ruoli sono definizioni condivise fra tutti i tenant; le ASSEGNAZIONI
 * (model_has_roles) porteranno il team id per-ente. Il seeder è idempotente
 * (firstOrCreate) e sicuro da rieseguire.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Guard di riferimento: unica fonte di verità condivisa con il bypass
     * Super Admin in AppServiceProvider (che interroga lo stesso config).
     * Se AUTH_GUARD cambiasse via env, entrambe le parti restano allineate.
     */
    private function guard(): string
    {
        $guard = config('auth.defaults.guard');

        return is_string($guard) ? $guard : 'web';
    }

    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);

        // I ruoli/permessi vanno creati fuori da qualunque contesto team,
        // così restano globali (team_id null).
        $registrar->setPermissionsTeamId(null);

        $this->createPermissions();
        $this->createRoles();
        $this->syncRolePermissions();

        $registrar->forgetCachedPermissions();
    }

    private function createPermissions(): void
    {
        foreach (PermissionName::cases() as $permission) {
            Permission::firstOrCreate([
                'name' => $permission->value,
                'guard_name' => $this->guard(),
            ]);
        }
    }

    private function createRoles(): void
    {
        foreach (RoleName::cases() as $role) {
            Role::firstOrCreate([
                'name' => $role->value,
                'guard_name' => $this->guard(),
            ]);
        }
    }

    private function syncRolePermissions(): void
    {
        $this->roleFor(RoleName::OrgOwner)
            ->syncPermissions($this->permissionValues(...PermissionName::cases()));

        $this->roleFor(RoleName::OrgStaff)
            ->syncPermissions($this->permissionValues(
                PermissionName::AnimalView,
                PermissionName::AnimalCreate,
                PermissionName::AnimalUpdate,
                PermissionName::AnimalDelete,
                PermissionName::MedicalRecordView,
                PermissionName::MedicalRecordCreate,
                PermissionName::MedicalRecordUpdate,
                PermissionName::MedicalRecordDelete,
                PermissionName::ApplicationView,
                PermissionName::ApplicationManage,
            ));

        $this->roleFor(RoleName::OrgVolunteer)
            ->syncPermissions($this->permissionValues(
                PermissionName::AnimalView,
                PermissionName::MedicalRecordView,
                PermissionName::ApplicationView,
            ));

        // Adopter e SuperAdmin non hanno permessi d'ente espliciti.
        // (SuperAdmin bypassa via Gate::before in AppServiceProvider.)
        $this->roleFor(RoleName::Adopter)->syncPermissions([]);
        $this->roleFor(RoleName::SuperAdmin)->syncPermissions([]);
    }

    private function roleFor(RoleName $role): Role
    {
        /** @var Role $model */
        $model = Role::query()
            ->where('name', $role->value)
            ->where('guard_name', $this->guard())
            ->firstOrFail();

        return $model;
    }

    /**
     * @return list<string>
     */
    private function permissionValues(PermissionName ...$permissions): array
    {
        return array_map(
            static fn (PermissionName $permission): string => $permission->value,
            $permissions,
        );
    }
}
