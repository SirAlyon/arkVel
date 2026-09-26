<?php

declare(strict_types=1);

use App\Actions\Organizations\AddOrganizationMember;
use App\Enums\MembershipRole;
use App\Enums\OrganizationStatus;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Http\Middleware\SetPermissionsTeamFromTenant;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

// Nit 2: azzera lo stato in-memory del team id sul registrar spatie dopo ogni
// test, così i test restano robusti a riordini ed evitiamo falsi positivi da
// team id residuo lasciato da chi imposta esplicitamente il contesto team.
afterEach(function () {
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

/**
 * Il pannello shelter (per risolvere il panel id in canAccessPanel/getTenants).
 */
function shelterPanel(): Panel
{
    return Filament::getPanel('shelter');
}

/**
 * Aggiunge un utente a un ente con ruolo/verifica dati, delegando all'Action.
 */
function addMember(
    Organization $organization,
    User $user,
    MembershipRole $role = MembershipRole::Staff,
    bool $verified = true,
): void {
    app(AddOrganizationMember::class)->handle(
        organization: $organization,
        user: $user,
        role: $role,
        verified: $verified,
    );
}

it('redirects a guest visiting the shelter panel to its login', function () {
    $this->get('/manage')
        ->assertRedirect('/manage/login');
});

it('lets a verified member access the shelter panel', function () {
    $organization = Organization::factory()->verified()->create();
    $user = User::factory()->create();
    addMember($organization, $user);

    expect($user->canAccessPanel(shelterPanel()))->toBeTrue();
});

it('exposes only the organizations the user is a verified member of as tenants', function () {
    $orgA = Organization::factory()->verified()->create();
    $orgB = Organization::factory()->verified()->create();
    $user = User::factory()->create();
    addMember($orgA, $user);

    $tenants = $user->getTenants(shelterPanel());

    expect($tenants->pluck('id')->all())->toContain($orgA->id)
        ->and($tenants->pluck('id')->all())->not->toContain($orgB->id);
});

it('lets a verified member operate an organization still Pending verification', function () {
    // INVARIANTE di prodotto: un ente Pending è operabile dai suoi membri
    // verificati (self-service pre-verifica). La verifica Super Admin gaterà
    // la visibilità PUBBLICA del catalogo in Fase 2, non la gestione interna.
    $organization = Organization::factory()->pending()->create();
    $user = User::factory()->create();
    addMember($organization, $user);

    expect($organization->status)->toBe(OrganizationStatus::Pending)
        ->and($user->canAccessTenant($organization))->toBeTrue()
        ->and($user->canAccessPanel(shelterPanel()))->toBeTrue()
        ->and($user->getTenants(shelterPanel())->pluck('id')->all())
        ->toContain($organization->id);
});

it('denies a verified member operating a suspended organization', function () {
    // Contraltare dell'invariante Pending: solo Suspended blocca l'accesso.
    $organization = Organization::factory()->suspended()->create();
    $user = User::factory()->create();
    addMember($organization, $user);

    expect($organization->status)->toBe(OrganizationStatus::Suspended)
        ->and($user->canAccessTenant($organization))->toBeFalse()
        ->and($user->canAccessPanel(shelterPanel()))->toBeFalse()
        ->and($user->getTenants(shelterPanel())->pluck('id')->all())
        ->not->toContain($organization->id);
});

it('denies access to an unverified member', function () {
    $organization = Organization::factory()->verified()->create();
    $user = User::factory()->create();
    addMember($organization, $user, verified: false);

    expect($user->canAccessPanel(shelterPanel()))->toBeFalse()
        ->and($user->canAccessTenant($organization))->toBeFalse();
});

it('denies canAccessTenant for an organization the user is not a member of', function () {
    $organization = Organization::factory()->verified()->create();
    $user = User::factory()->create();

    expect($user->canAccessTenant($organization))->toBeFalse();
});

it('denies canAccessTenant for a suspended organization even to a verified member', function () {
    $organization = Organization::factory()->suspended()->create();
    $user = User::factory()->create();
    addMember($organization, $user);

    expect($user->canAccessTenant($organization))->toBeFalse();
});

it('excludes suspended organizations from the tenant list', function () {
    $active = Organization::factory()->verified()->create();
    $suspended = Organization::factory()->suspended()->create();
    $user = User::factory()->create();
    addMember($active, $user);
    addMember($suspended, $user);

    $tenants = $user->getTenants(shelterPanel());

    expect($tenants->pluck('id')->all())->toContain($active->id)
        ->and($tenants->pluck('id')->all())->not->toContain($suspended->id);
});

it('lets a super admin access the shelter panel', function () {
    $user = User::factory()->create();

    // Il ruolo di piattaforma porta comunque un team id (colonna NOT NULL);
    // il team è irrilevante per il rilevamento del Super Admin.
    app(PermissionRegistrar::class)->setPermissionsTeamId(1);
    $user->assignRole(RoleName::SuperAdmin->value);

    expect($user->canAccessPanel(shelterPanel()))->toBeTrue();
});

it('wires the spatie team id to the current tenant via middleware', function () {
    $orgA = Organization::factory()->verified()->create();
    $owner = User::factory()->create();
    addMember($orgA, $owner, role: MembershipRole::Owner);

    // setTenant emette TenantSet con l'utente corrente: serve un utente autenticato.
    $this->actingAs($owner);
    Filament::setCurrentPanel(shelterPanel());
    Filament::setTenant($orgA);

    $middleware = app(SetPermissionsTeamFromTenant::class);
    $response = $middleware->handle(Request::create('/manage'), fn (): Response => new Response);

    expect($response)->toBeInstanceOf(Response::class)
        ->and(app(PermissionRegistrar::class)->getPermissionsTeamId())->toBe($orgA->getKey());
});

it('scopes org staff permissions to the tenant resolved by the middleware', function () {
    $orgA = Organization::factory()->verified()->create();
    $orgB = Organization::factory()->verified()->create();
    // Membro (staff) solo di A; su B non ha alcun ruolo scoped.
    $user = User::factory()->create();
    addMember($orgA, $user, role: MembershipRole::Staff);

    // Un secondo utente autenticato con accesso a entrambi i tenant, così da
    // poter risolvere il tenant B senza dipendere dalla membership di $user.
    $operator = User::factory()->create();
    addMember($orgA, $operator, role: MembershipRole::Owner);
    addMember($orgB, $operator, role: MembershipRole::Owner);

    $this->actingAs($operator);
    Filament::setCurrentPanel(shelterPanel());

    $registrar = app(PermissionRegistrar::class);
    $middleware = app(SetPermissionsTeamFromTenant::class);

    // Contesto tenant A: lo staff ha animal.view.
    Filament::setTenant($orgA);
    $middleware->handle(Request::create('/manage'), fn (): Response => new Response);
    $registrar->forgetCachedPermissions();
    expect($user->can(PermissionName::AnimalView->value))->toBeTrue();

    // Contesto tenant B: $user non ha alcun ruolo scoped su B, niente permesso.
    Filament::setTenant($orgB);
    $middleware->handle(Request::create('/manage'), fn (): Response => new Response);
    $registrar->forgetCachedPermissions();
    expect($user->fresh()->can(PermissionName::AnimalView->value))->toBeFalse();
});
