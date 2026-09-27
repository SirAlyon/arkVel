<?php

declare(strict_types=1);

use App\Enums\AnimalStatus;
use App\Enums\FosterApplicationStatus;
use App\Enums\FosterPlacementStatus;
use App\Enums\FosterPlacementType;
use App\Enums\MembershipRole;
use App\Filament\Shelter\Resources\FosterApplications\Pages\ListFosterApplications;
use App\Filament\Shelter\Resources\FosterApplications\Pages\ViewFosterApplication;
use App\Models\AdopterProfile;
use App\Models\Animal;
use App\Models\FosterApplication;
use App\Models\FosterPlacement;
use App\Models\Organization;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\PermissionRegistrar;

// I helper member() e actAsInTenant() sono definiti una sola volta in
// tests/Pest.php (condivisi da tutti i test dello shelter): li riusiamo qui.
// Specchio di tests/Feature/Shelter/AdoptionApplicationResourceTest.php.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Notification::fake();
});

afterEach(function () {
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

/**
 * Richiesta di affido Submitted per un animale dell'ente indicato.
 */
function shelterFosterApplicationFor(Organization $organization): FosterApplication
{
    $animal = Animal::factory()->available()->forOrganization($organization)->create();

    return FosterApplication::factory()
        ->submitted()
        ->forAnimal($animal)
        ->create();
}

it('scopes the foster list to the current tenant and hides other tenants requests (cross-tenant isolation)', function () {
    $orgA = Organization::factory()->verified()->create();
    $orgB = Organization::factory()->verified()->create();

    $aRequests = collect(range(1, 3))->map(fn (): FosterApplication => shelterFosterApplicationFor($orgA));
    $bRequests = collect(range(1, 2))->map(fn (): FosterApplication => shelterFosterApplicationFor($orgB));

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(ListFosterApplications::class)
        ->assertOk()
        ->assertCanSeeTableRecords($aRequests)
        ->assertCanNotSeeTableRecords($bRequests);
});

it('lets staff with FosterApplicationManage advance a foster request via the table action and persists it', function () {
    $orgA = Organization::factory()->verified()->create();
    $application = shelterFosterApplicationFor($orgA);

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    // Submitted -> UnderReview è l'unica transizione staff offerta da Submitted.
    Livewire::test(ListFosterApplications::class)
        ->assertActionVisible(TestAction::make('advance')->table($application))
        ->callAction(
            TestAction::make('advance')->table($application),
            ['status' => FosterApplicationStatus::UnderReview->value],
        )
        ->assertHasNoActionErrors();

    expect($application->refresh()->statusEnum())->toBe(FosterApplicationStatus::UnderReview);

    // La transizione ha delegato ad AdvanceFosterApplication (activity log presente).
    expect(Activity::query()->where('description', 'foster_application.status_changed')->count())
        ->toBe(1);
});

it('approves a foster request under review via the advance action', function () {
    $orgA = Organization::factory()->verified()->create();

    $animal = Animal::factory()->available()->forOrganization($orgA)->create();
    $application = FosterApplication::factory()->underReview()->forAnimal($animal)->create();

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    // Da UnderReview lo staff può approvare: transizione legale.
    Livewire::test(ListFosterApplications::class)
        ->callAction(
            TestAction::make('advance')->table($application),
            ['status' => FosterApplicationStatus::Approved->value],
        )
        ->assertHasNoActionErrors();

    expect($application->refresh()->statusEnum())->toBe(FosterApplicationStatus::Approved);
})->group('transitions');

it('rejects a target that is not a legal staff transition (not offered by the select)', function () {
    $orgA = Organization::factory()->verified()->create();
    $application = shelterFosterApplicationFor($orgA);

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    // Da Submitted, Approved NON è offerto: il Select rifiuta il valore.
    Livewire::test(ListFosterApplications::class)
        ->callAction(
            TestAction::make('advance')->table($application),
            ['status' => FosterApplicationStatus::Approved->value],
        )
        ->assertHasActionErrors(['status']);

    expect($application->refresh()->statusEnum())->toBe(FosterApplicationStatus::Submitted);
});

it('starts a foster placement from an approved request and moves the animal to fostered', function () {
    $orgA = Organization::factory()->verified()->create();

    $animal = Animal::factory()->available()->forOrganization($orgA)->create(['name' => 'Luna']);
    $application = FosterApplication::factory()->approved()->forAnimal($animal)->create();

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(ListFosterApplications::class)
        ->assertActionVisible(TestAction::make('startPlacement')->table($application))
        ->callAction(
            TestAction::make('startPlacement')->table($application),
            [
                'type' => FosterPlacementType::LongTerm->value,
                'start_date' => now()->toDateString(),
                'notes' => 'Consegnato oggi.',
            ],
        )
        ->assertHasNoActionErrors()
        ->assertNotified();

    expect($application->refresh()->statusEnum())->toBe(FosterApplicationStatus::Active)
        ->and($animal->refresh()->status)->toBe(AnimalStatus::Fostered)
        ->and(FosterPlacement::query()->where('foster_application_id', $application->getKey())->exists())->toBeTrue();
})->group('transitions');

it('hides the start-placement action when the request is not approved', function () {
    $orgA = Organization::factory()->verified()->create();
    // Submitted: l'avvio non è offerto.
    $application = shelterFosterApplicationFor($orgA);

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(ListFosterApplications::class)
        ->assertActionHidden(TestAction::make('startPlacement')->table($application));
});

it('ends an active foster placement and returns the animal to available', function () {
    $orgA = Organization::factory()->verified()->create();

    $animal = Animal::factory()->forOrganization($orgA)->create(['status' => AnimalStatus::Fostered]);
    $application = FosterApplication::factory()->active()->forAnimal($animal)->create();
    $placement = FosterPlacement::factory()->active()->forApplication($application)->create();

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(ListFosterApplications::class)
        ->assertActionVisible(TestAction::make('endPlacement')->table($application))
        ->callAction(
            TestAction::make('endPlacement')->table($application),
            ['end_date' => now()->toDateString(), 'notes' => 'Rientrato in sede.'],
        )
        ->assertHasNoActionErrors()
        ->assertNotified();

    expect($application->refresh()->statusEnum())->toBe(FosterApplicationStatus::Completed)
        ->and($placement->refresh()->status)->toBe(FosterPlacementStatus::Ended)
        ->and($animal->refresh()->status)->toBe(AnimalStatus::Available);
})->group('transitions');

it('hides the end-placement action when there is no active placement', function () {
    $orgA = Organization::factory()->verified()->create();
    // Approved senza placement: la chiusura non è offerta.
    $animal = Animal::factory()->available()->forOrganization($orgA)->create();
    $application = FosterApplication::factory()->approved()->forAnimal($animal)->create();

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(ListFosterApplications::class)
        ->assertActionHidden(TestAction::make('endPlacement')->table($application));
});

it('assigns the foster request to a verified member of the tenant org', function () {
    $orgA = Organization::factory()->verified()->create();
    $application = shelterFosterApplicationFor($orgA);

    $staff = member($orgA, MembershipRole::Staff);
    $colleague = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(ListFosterApplications::class)
        ->callAction(
            TestAction::make('assign')->table($application),
            ['assigned_to' => $colleague->getKey()],
        )
        ->assertHasNoActionErrors();

    expect($application->refresh()->assigned_to)->toBe($colleague->getKey());
});

it('does not allow assigning a user who is not a verified member of the tenant (foster)', function () {
    $orgA = Organization::factory()->verified()->create();
    $orgB = Organization::factory()->verified()->create();
    $application = shelterFosterApplicationFor($orgA);

    $staff = member($orgA, MembershipRole::Staff);
    // Un membro di un ALTRO ente: non deve essere tra i candidati selezionabili.
    $outsider = member($orgB, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(ListFosterApplications::class)
        ->callAction(
            TestAction::make('assign')->table($application),
            ['assigned_to' => $outsider->getKey()],
        )
        ->assertHasActionErrors(['assigned_to']);

    expect($application->refresh()->assigned_to)->toBeNull();
});

it('lets staff edit the internal foster staff notes', function () {
    $orgA = Organization::factory()->verified()->create();
    $application = shelterFosterApplicationFor($orgA);

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(ListFosterApplications::class)
        ->callAction(
            TestAction::make('editNotes')->table($application),
            ['staff_notes' => 'Verificata la casa dell\'affidatario.'],
        )
        ->assertHasNoActionErrors();

    expect($application->refresh()->staff_notes)->toBe('Verificata la casa dell\'affidatario.');
});

it('lets a volunteer view the foster list but hides the manage actions', function () {
    $orgA = Organization::factory()->verified()->create();
    $application = shelterFosterApplicationFor($orgA);

    // Il volontario ha solo FosterApplicationView (no FosterApplicationManage).
    $volunteer = member($orgA, MembershipRole::Volunteer);
    actAsInTenant($volunteer, $orgA);

    Livewire::test(ListFosterApplications::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$application])
        ->assertActionHidden(TestAction::make('advance')->table($application))
        ->assertActionHidden(TestAction::make('assign')->table($application))
        ->assertActionHidden(TestAction::make('editNotes')->table($application));
});

it('lets staff view a foster request of their own org', function () {
    $orgA = Organization::factory()->verified()->create();
    $application = shelterFosterApplicationFor($orgA);

    // Un profilo affidatario completo per popolare la sezione privacy-aware.
    AdopterProfile::factory()->complete()->forUser($application->user)->create();

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(ViewFosterApplication::class, ['record' => $application->getKey()])
        ->assertOk();
});

it('forbids viewing a foster request that belongs to another tenant (record binding respects tenancy)', function () {
    $orgA = Organization::factory()->verified()->create();
    $orgB = Organization::factory()->verified()->create();

    $applicationB = shelterFosterApplicationFor($orgB);

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    // Aprire nel tenant A la view di una richiesta di B non la risolve: il record è
    // fuori dallo scope del tenant corrente (404). È l'isolamento più forte.
    Livewire::test(ViewFosterApplication::class, ['record' => $applicationB->getKey()])
        ->assertNotFound();
});
