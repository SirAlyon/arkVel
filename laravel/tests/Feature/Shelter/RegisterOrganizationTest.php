<?php

declare(strict_types=1);

use App\Enums\MembershipRole;
use App\Enums\OrganizationStatus;
use App\Enums\OrganizationType;
use App\Enums\RoleName;
use App\Filament\Shelter\Pages\Tenancy\RegisterOrganization;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    // Il pannello shelter deve essere quello corrente perché la pagina di
    // registrazione tenant risolva il model tenant (Filament::getTenantModel()).
    Filament::setCurrentPanel(Filament::getPanel('shelter'));
});

// Nit 2: azzera lo stato in-memory del team id sul registrar spatie dopo ogni
// test, così restano robusti a riordini ed evitiamo falsi positivi da residui.
afterEach(function () {
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

it('registers a new organization with the current user as verified owner', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(RegisterOrganization::class)
        ->fillForm([
            'name' => 'Rifugio Speranza',
            'type' => OrganizationType::Rifugio->value,
            'country' => 'IT',
            'city' => 'Milano',
        ])
        ->call('register')
        ->assertHasNoFormErrors();

    // Ente creato in stato Pending, con slug generato dal name.
    $organization = Organization::query()->where('name', 'Rifugio Speranza')->sole();
    expect($organization->status)->toBe(OrganizationStatus::Pending)
        ->and($organization->slug)->toBe('rifugio-speranza');

    // L'utente autenticato è membro Owner verificato dell'ente.
    $organization->refresh();
    $membership = $organization->users()
        ->wherePivot('user_id', $user->getKey())
        ->firstOrFail()
        ->getRelationValue('pivot');

    expect($membership->role)->toBe(MembershipRole::Owner)
        ->and($membership->verified)->toBeTrue();

    // Ruolo spatie "Org Owner" assegnato NEL contesto team dell'ente creato.
    app(PermissionRegistrar::class)->setPermissionsTeamId($organization->getKey());
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    expect($user->fresh()->hasRole(RoleName::OrgOwner->value))->toBeTrue();
});

it('creates the organization and its membership atomically (no orphan org)', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(RegisterOrganization::class)
        ->fillForm([
            'name' => 'Oasi Felina',
            'type' => OrganizationType::Associazione->value,
            'country' => 'IT',
            'city' => 'Torino',
        ])
        ->call('register')
        ->assertHasNoFormErrors();

    // Esistono SIA l'ente SIA la membership: nessun ente orfano senza owner.
    $organization = Organization::query()->where('name', 'Oasi Felina')->sole();

    expect($organization->users()->count())->toBe(1)
        ->and(
            $organization->users()
                ->wherePivot('user_id', $user->getKey())
                ->wherePivot('role', MembershipRole::Owner->value)
                ->exists()
        )->toBeTrue();
});
