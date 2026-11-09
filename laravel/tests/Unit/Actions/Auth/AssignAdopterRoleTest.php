<?php

declare(strict_types=1);

use App\Actions\Auth\AssignAdopterRole;
use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

afterEach(function (): void {
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

/** @return list<int> team id delle assegnazioni del ruolo Adopter all'utente */
function adopterRoleTeams(User $user): array
{
    return DB::table('model_has_roles')
        ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
        ->where('model_has_roles.model_id', $user->getKey())
        ->where('roles.name', RoleName::Adopter->value)
        ->pluck('model_has_roles.team_id')
        ->map(static fn (mixed $team): int => (int) $team)
        ->all();
}

it('assigns the Adopter role under the platform team sentinel', function (): void {
    $user = User::factory()->create();

    app(AssignAdopterRole::class)->handle($user);

    expect(adopterRoleTeams($user))->toBe([RoleName::PLATFORM_TEAM_ID]);
});

it('is idempotent, whatever the current team id', function (): void {
    $user = User::factory()->create();
    $action = app(AssignAdopterRole::class);

    $action->handle($user);
    app(PermissionRegistrar::class)->setPermissionsTeamId(42);
    $action->handle($user);

    expect(adopterRoleTeams($user))->toBe([RoleName::PLATFORM_TEAM_ID]);
});

it('restores the previous team id', function (): void {
    app(PermissionRegistrar::class)->setPermissionsTeamId(7);

    app(AssignAdopterRole::class)->handle(User::factory()->create());

    expect(app(PermissionRegistrar::class)->getPermissionsTeamId())->toBe(7);
});
