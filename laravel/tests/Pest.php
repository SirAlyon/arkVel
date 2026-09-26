<?php

declare(strict_types=1);

use App\Actions\Organizations\AddOrganizationMember;
use App\Enums\MembershipRole;
use App\Models\Organization;
use App\Models\User;
use Filament\Facades\Filament;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature');
pest()->extend(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Helper condivisi per i test del pannello shelter (tenant-scoped)
|--------------------------------------------------------------------------
|
| Definiti qui una sola volta (Pest.php e' sempre caricato) cosi' ogni feature
| test dello shelter puo' riusarli senza ridichiararli.
*/

if (! function_exists('member')) {
    /**
     * Aggiunge un utente come membro verificato dell'ente col ruolo indicato,
     * delegando all'Action (crea anche il ruolo spatie team-scoped).
     */
    function member(Organization $organization, MembershipRole $role): User
    {
        $user = User::factory()->create();

        app(AddOrganizationMember::class)->handle(
            organization: $organization,
            user: $user,
            role: $role,
            verified: true,
        );

        return $user;
    }
}

if (! function_exists('actAsInTenant')) {
    /**
     * Colloca l'utente nel contesto del pannello shelter e del tenant indicato,
     * allineando il team id spatie (come fa il middleware in produzione).
     */
    function actAsInTenant(User $user, Organization $tenant): void
    {
        test()->actingAs($user);

        $panel = Filament::getPanel('shelter');
        // boot() registra gli observer di tenancy (auto-associazione del tenant
        // in creazione, global scope di scoping). In produzione lo fa il kernel
        // al boot dei pannelli; nei test lo forziamo per deterministicita.
        $panel->boot();

        Filament::setCurrentPanel($panel);
        Filament::setTenant($tenant);

        $registrar = app(PermissionRegistrar::class);
        $registrar->setPermissionsTeamId($tenant->getKey());
        $registrar->forgetCachedPermissions();
    }
}
