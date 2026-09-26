<?php

declare(strict_types=1);

use App\Actions\Organizations\AddOrganizationMember;
use App\Enums\MedicalRecordType;
use App\Enums\MembershipRole;
use App\Filament\Shelter\Resources\Animals\Pages\EditAnimal;
use App\Filament\Shelter\Resources\Animals\RelationManagers\MedicalRecordsRelationManager;
use App\Models\Animal;
use App\Models\MedicalRecord;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

afterEach(function () {
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

/**
 * Aggiunge un utente come membro verificato dell'ente col ruolo indicato.
 */
function medicalRmMember(Organization $organization, MembershipRole $role): User
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

/**
 * Colloca l'utente nel contesto del pannello shelter e del tenant indicato,
 * allineando il team id spatie (come fa il middleware in produzione).
 */
function medicalRmActAsInTenant(User $user, Organization $tenant): void
{
    test()->actingAs($user);

    $panel = Filament::getPanel('shelter');
    $panel->boot();

    Filament::setCurrentPanel($panel);
    Filament::setTenant($tenant);

    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($tenant->getKey());
    $registrar->forgetCachedPermissions();
}

it('lets staff create and see a medical record under an animal of their tenant', function () {
    $orgA = Organization::factory()->verified()->create();
    $animalA = Animal::factory()->forOrganization($orgA)->create();

    $staff = medicalRmMember($orgA, MembershipRole::Staff);
    medicalRmActAsInTenant($staff, $orgA);

    Livewire::test(MedicalRecordsRelationManager::class, [
        'ownerRecord' => $animalA,
        'pageClass' => EditAnimal::class,
    ])
        ->assertOk()
        ->callAction(TestAction::make('create')->table(), [
            'type' => MedicalRecordType::Vaccination->value,
            'date' => now()->toDateString(),
            'vet_name' => 'Dott. Mario Rossi',
            'description' => 'Vaccino annuale.',
        ])
        ->assertHasNoActionErrors();

    $record = MedicalRecord::query()->sole();

    expect($record->animal_id)->toBe($animalA->getKey())
        ->and($record->type)->toBe(MedicalRecordType::Vaccination)
        ->and($record->vet_name)->toBe('Dott. Mario Rossi');
});

it('shows only the medical records of the current animal, not of others', function () {
    $orgA = Organization::factory()->verified()->create();
    $animalA = Animal::factory()->forOrganization($orgA)->create();
    $otherAnimal = Animal::factory()->forOrganization($orgA)->create();

    $ownRecords = MedicalRecord::factory()->count(2)->forAnimal($animalA)->create();
    $foreignRecords = MedicalRecord::factory()->count(3)->forAnimal($otherAnimal)->create();

    $staff = medicalRmMember($orgA, MembershipRole::Staff);
    medicalRmActAsInTenant($staff, $orgA);

    Livewire::test(MedicalRecordsRelationManager::class, [
        'ownerRecord' => $animalA,
        'pageClass' => EditAnimal::class,
    ])
        ->assertOk()
        ->assertCanSeeTableRecords($ownRecords)
        ->assertCanNotSeeTableRecords($foreignRecords);
});

it('lets a volunteer see the list but hides create, edit and delete actions', function () {
    $orgA = Organization::factory()->verified()->create();
    $animalA = Animal::factory()->forOrganization($orgA)->create();
    $records = MedicalRecord::factory()->count(2)->forAnimal($animalA)->create();

    $volunteer = medicalRmMember($orgA, MembershipRole::Volunteer);
    medicalRmActAsInTenant($volunteer, $orgA);

    Livewire::test(MedicalRecordsRelationManager::class, [
        'ownerRecord' => $animalA,
        'pageClass' => EditAnimal::class,
    ])
        ->assertOk()
        ->assertCanSeeTableRecords($records)
        ->assertActionHidden(TestAction::make('create')->table())
        ->assertActionHidden(TestAction::make('edit')->table($records->first()))
        ->assertActionHidden(TestAction::make('delete')->table($records->first()));
});

it('denies a staff of another tenant any access to the medical records of a foreign animal', function () {
    // Ente A con animale e cartelle proprie.
    $orgA = Organization::factory()->verified()->create();
    $animalA = Animal::factory()->forOrganization($orgA)->create();
    $recordsA = MedicalRecord::factory()->count(2)->forAnimal($animalA)->create();

    // Ente B, del tutto distinto, con il proprio staff verificato.
    $orgB = Organization::factory()->verified()->create();
    $staffB = medicalRmMember($orgB, MembershipRole::Staff);

    // Lo staff di B opera nel proprio contesto/tenant (B). In produzione il
    // route binding tenant-scoped impedisce di risolvere l'animale di A nel
    // tenant B, quindi il RM non è nemmeno montabile su $animalA. Qui, a
    // difesa in profondità, verifichiamo che comunque — anche potendo
    // riferire i record di A — lo staff di B non abbia alcuna autorizzazione
    // sulle cartelle di A a livello di componente/Policy.
    medicalRmActAsInTenant($staffB, $orgB);

    $foreignRecord = $recordsA->first();

    // Nessun permesso di lettura né di scrittura sulle cartelle di A.
    expect($staffB->can('view', $foreignRecord))->toBeFalse()
        ->and($staffB->can('update', $foreignRecord))->toBeFalse()
        ->and($staffB->can('delete', $foreignRecord))->toBeFalse();

    // E se pure il RM venisse montato sull'animale di A (scenario non
    // raggiungibile in produzione), le azioni di scrittura restano nascoste.
    Livewire::test(MedicalRecordsRelationManager::class, [
        'ownerRecord' => $animalA,
        'pageClass' => EditAnimal::class,
    ])
        ->assertOk()
        ->assertActionHidden(TestAction::make('edit')->table($foreignRecord))
        ->assertActionHidden(TestAction::make('delete')->table($foreignRecord));
});

it('lets staff edit and delete a record via actions honoring the policy', function () {
    $orgA = Organization::factory()->verified()->create();
    $animalA = Animal::factory()->forOrganization($orgA)->create();
    $record = MedicalRecord::factory()->forAnimal($animalA)->create([
        'type' => MedicalRecordType::Checkup,
    ]);

    $staff = medicalRmMember($orgA, MembershipRole::Staff);
    medicalRmActAsInTenant($staff, $orgA);

    Livewire::test(MedicalRecordsRelationManager::class, [
        'ownerRecord' => $animalA,
        'pageClass' => EditAnimal::class,
    ])
        ->assertActionVisible(TestAction::make('edit')->table($record))
        ->assertActionVisible(TestAction::make('delete')->table($record))
        ->callAction(TestAction::make('edit')->table($record), [
            'type' => MedicalRecordType::Treatment->value,
            'date' => $record->date->toDateString(),
        ])
        ->assertHasNoActionErrors();

    expect($record->refresh()->type)->toBe(MedicalRecordType::Treatment);
});
