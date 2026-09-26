<?php

declare(strict_types=1);

use App\Enums\MembershipRole;
use App\Enums\OrganizationStatus;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Animal;
use App\Models\MedicalRecord;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Le foto placeholder finiscono su un disk finto: nessuna scrittura reale
    // e nessuna conversione su disco durante i test.
    Storage::fake('public');

    // Esegue l'intero orchestratore (ruoli + dati demo) come in produzione.
    $this->seed(DatabaseSeeder::class);
});

/**
 * Imposta il contesto team per verificare i ruoli spatie team-scoped.
 */
function seederWithTeam(?int $teamId): void
{
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($teamId);
    $registrar->forgetCachedPermissions();
}

it('creates at least three verified organizations', function () {
    expect(Organization::where('status', OrganizationStatus::Verified)->count())
        ->toBeGreaterThanOrEqual(3);
});

it('creates exactly one super admin with known credentials', function () {
    // Il ruolo Super Admin è di piattaforma: lo rileviamo con una query diretta
    // sul pivot (team-independent), come fa il bypass in AppServiceProvider. Le
    // scope spatie (User::role / roles()) sono team-scoped e mascherebbero
    // l'assegnazione fatta col team sentinel di piattaforma.
    $superAdminAssignments = DB::table('model_has_roles')
        ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
        ->where('roles.name', RoleName::SuperAdmin->value)
        ->where('model_has_roles.model_type', (new User)->getMorphClass())
        ->pluck('model_has_roles.model_id');

    expect($superAdminAssignments)->toHaveCount(1);

    $admin = User::findOrFail($superAdminAssignments->first());

    expect($admin->email)->toBe('admin@arkvel.test')
        ->and($admin->email_verified_at)->not->toBeNull();

    // Verifica autoritativa: il bypass Gate::before (team-independent) deve
    // concedere qualunque permesso al Super Admin, in qualsiasi contesto team.
    seederWithTeam(999);
    expect($admin->can(PermissionName::OrganizationUpdate->value))->toBeTrue()
        ->and($admin->can(PermissionName::AnimalDelete->value))->toBeTrue();
});

it('gives every organization a verified owner, staff and volunteer with the correct team-scoped role', function () {
    Organization::query()->each(function (Organization $organization): void {
        $verifiedMembers = $organization->verifiedStaff()->get();

        expect($verifiedMembers->count())->toBeGreaterThanOrEqual(3);

        $rolesByMembership = [
            MembershipRole::Owner->value => RoleName::OrgOwner,
            MembershipRole::Staff->value => RoleName::OrgStaff,
            MembershipRole::Volunteer->value => RoleName::OrgVolunteer,
        ];

        foreach ($rolesByMembership as $membershipRole => $expectedRole) {
            $member = $organization->verifiedStaff()
                ->wherePivot('role', $membershipRole)
                ->first();

            expect($member)->not->toBeNull("Manca il membro {$membershipRole} nell'ente {$organization->slug}");

            seederWithTeam($organization->getKey());

            expect($member->fresh()->hasRole($expectedRole->value))
                ->toBeTrue("Il ruolo {$expectedRole->value} non è assegnato nel team {$organization->slug}");
        }
    });
});

it('creates animals belonging to the seeded organizations', function () {
    expect(Animal::count())->toBeGreaterThan(0);

    $organizationIds = Organization::query()->pluck('id')->all();

    Animal::query()->each(function (Animal $animal) use ($organizationIds): void {
        expect($organizationIds)->toContain($animal->organization_id);
    });
});

it('attaches at least one media (avatar or photos) to every seeded animal', function () {
    Animal::query()->each(function (Animal $animal): void {
        $mediaCount = $animal->getMedia('avatar')->count()
            + $animal->getMedia('photos')->count();

        expect($mediaCount)->toBeGreaterThan(0, "L'animale {$animal->slug} non ha media");
    });
});

it('creates medical records linked to existing animals', function () {
    expect(MedicalRecord::count())->toBeGreaterThan(0);

    $animalIds = Animal::query()->pluck('id')->all();

    MedicalRecord::query()->each(function (MedicalRecord $record) use ($animalIds): void {
        expect($animalIds)->toContain($record->animal_id);
    });
});

it('gives a significant proportion of animals a medical record', function () {
    // REGRESSIONE M2: con la logica boolean invertita quasi nessun animale
    // riceveva una cartella. Il target è ~60%: verifichiamo che una frazione
    // ragionevole (soglia prudente al 30%, robusta alla varianza di Faker senza
    // seed) degli animali abbia almeno una cartella sanitaria.
    $totalAnimals = Animal::count();
    $animalsWithRecords = MedicalRecord::query()->distinct()->count('animal_id');

    expect($totalAnimals)->toBeGreaterThan(0)
        ->and($animalsWithRecords)->toBeGreaterThanOrEqual((int) ceil($totalAnimals * 0.3));
});

it('creates a known staff user who is a verified member of its organization', function () {
    $milano = Organization::where('slug', 'canile-comunale-di-milano')->firstOrFail();

    $staff = User::where('email', 'staff+milano@arkvel.test')->first();

    expect($staff)->not->toBeNull()
        ->and($staff->email_verified_at)->not->toBeNull()
        ->and($staff->isVerifiedMemberOf($milano))->toBeTrue();

    seederWithTeam($milano->getKey());
    expect($staff->fresh()->hasRole(RoleName::OrgStaff->value))->toBeTrue();
});
