<?php

declare(strict_types=1);

use App\Enums\AnimalStatus;
use App\Enums\ApplicationStatus;
use App\Enums\MembershipRole;
use App\Filament\Shelter\Resources\AdoptionApplications\Pages\ListAdoptionApplications;
use App\Filament\Shelter\Resources\AdoptionApplications\Pages\ViewAdoptionApplication;
use App\Models\AdopterProfile;
use App\Models\Adoption;
use App\Models\AdoptionApplication;
use App\Models\Animal;
use App\Models\Organization;
use App\Support\Contracts\ContractGenerator;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\PermissionRegistrar;

// I helper member() e actAsInTenant() sono definiti una sola volta in
// tests/Pest.php (condivisi da tutti i test dello shelter): li riusiamo qui.

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

afterEach(function () {
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

/**
 * Candidatura Submitted per un animale dell'ente indicato.
 */
function shelterApplicationFor(Organization $organization): AdoptionApplication
{
    $animal = Animal::factory()->available()->forOrganization($organization)->create();

    return AdoptionApplication::factory()
        ->submitted()
        ->forAnimal($animal)
        ->create();
}

/**
 * Candidatura in AdoptionPending con l'animale RISERVATO (AdoptionPending),
 * pronta per la finalizzazione dello staff.
 */
function shelterPendingApplicationFor(Organization $organization): AdoptionApplication
{
    $animal = Animal::factory()
        ->forOrganization($organization)
        ->create(['status' => AnimalStatus::AdoptionPending]);

    return AdoptionApplication::factory()
        ->adoptionPending()
        ->forAnimal($animal)
        ->create();
}

it('scopes the list to the current tenant and hides other tenants applications (cross-tenant isolation)', function () {
    $orgA = Organization::factory()->verified()->create();
    $orgB = Organization::factory()->verified()->create();

    $aApplications = collect(range(1, 3))->map(fn (): AdoptionApplication => shelterApplicationFor($orgA));
    $bApplications = collect(range(1, 2))->map(fn (): AdoptionApplication => shelterApplicationFor($orgB));

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(ListAdoptionApplications::class)
        ->assertOk()
        ->assertCanSeeTableRecords($aApplications)
        ->assertCanNotSeeTableRecords($bApplications);
});

it('lets staff with ApplicationManage advance an application via the table action and persists it', function () {
    $orgA = Organization::factory()->verified()->create();
    $application = shelterApplicationFor($orgA);

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    // Submitted -> UnderReview e' l'unica transizione staff offerta da Submitted.
    Livewire::test(ListAdoptionApplications::class)
        ->assertActionVisible(TestAction::make('advance')->table($application))
        ->callAction(
            TestAction::make('advance')->table($application),
            ['status' => ApplicationStatus::UnderReview->value],
        )
        ->assertHasNoActionErrors();

    expect($application->refresh()->statusEnum())->toBe(ApplicationStatus::UnderReview);

    // La transizione ha delegato ad AdvanceApplication (activity log presente).
    expect(Activity::query()->where('description', 'application.status_changed')->count())
        ->toBe(1);
});

it('offers only the legal staff transitions for the current state', function () {
    $orgA = Organization::factory()->verified()->create();

    $animal = Animal::factory()->available()->forOrganization($orgA)->create();
    $application = AdoptionApplication::factory()->underReview()->forAnimal($animal)->create();

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    // Da UnderReview lo staff puo' portare a Interview: transizione legale.
    Livewire::test(ListAdoptionApplications::class)
        ->callAction(
            TestAction::make('advance')->table($application),
            ['status' => ApplicationStatus::Interview->value],
        )
        ->assertHasNoActionErrors();

    expect($application->refresh()->statusEnum())->toBe(ApplicationStatus::Interview);
})->group('transitions');

it('rejects a target that is not a legal staff transition (not offered by the select)', function () {
    $orgA = Organization::factory()->verified()->create();
    $application = shelterApplicationFor($orgA);

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    // Da Submitted, Approved NON e' offerto: il Select rifiuta il valore.
    Livewire::test(ListAdoptionApplications::class)
        ->callAction(
            TestAction::make('advance')->table($application),
            ['status' => ApplicationStatus::Approved->value],
        )
        ->assertHasActionErrors(['status']);

    expect($application->refresh()->statusEnum())->toBe(ApplicationStatus::Submitted);
});

it('surfaces a danger notification and does not change state when the single-adoption guard blocks the transition', function () {
    $orgA = Organization::factory()->verified()->create();

    // Due candidature APPROVATE per lo STESSO animale. La prima passa in
    // AdoptionPending; portare anche la seconda in AdoptionPending deve scattare
    // la guardia "una sola adozione attiva per animale" (AnimalAlreadyHasPendingAdoption).
    $animal = Animal::factory()->available()->forOrganization($orgA)->create();
    $first = AdoptionApplication::factory()->adoptionPending()->forAnimal($animal)->create();
    $second = AdoptionApplication::factory()->approved()->forAnimal($animal)->create();

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    // Approved -> AdoptionPending e' legale nel grafo, ma la guardia di dominio
    // lo blocca: l'eccezione viene intercettata (notifica danger), niente crash.
    Livewire::test(ListAdoptionApplications::class)
        ->callAction(
            TestAction::make('advance')->table($second),
            ['status' => ApplicationStatus::AdoptionPending->value],
        )
        ->assertHasNoActionErrors()
        ->assertNotified();

    // Stato invariato: la seconda resta Approved, la prima resta AdoptionPending.
    expect($second->refresh()->statusEnum())->toBe(ApplicationStatus::Approved)
        ->and($first->refresh()->statusEnum())->toBe(ApplicationStatus::AdoptionPending);
})->group('transitions');

it('assigns the application to a verified member of the tenant org', function () {
    $orgA = Organization::factory()->verified()->create();
    $application = shelterApplicationFor($orgA);

    $staff = member($orgA, MembershipRole::Staff);
    $colleague = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(ListAdoptionApplications::class)
        ->callAction(
            TestAction::make('assign')->table($application),
            ['assigned_to' => $colleague->getKey()],
        )
        ->assertHasNoActionErrors();

    expect($application->refresh()->assigned_to)->toBe($colleague->getKey());
});

it('does not allow assigning a user who is not a verified member of the tenant', function () {
    $orgA = Organization::factory()->verified()->create();
    $orgB = Organization::factory()->verified()->create();
    $application = shelterApplicationFor($orgA);

    $staff = member($orgA, MembershipRole::Staff);
    // Un membro di un ALTRO ente: non deve essere tra i candidati selezionabili
    // (le opzioni derivano da Organization::verifiedStaff del tenant corrente).
    $outsider = member($orgB, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    // Il non-membro non e' tra le opzioni del Select (derivate da
    // Organization::verifiedStaff del tenant): forzarne l'id fallisce la
    // validazione del campo ("selezione non valida"), quindi assigned_to resta
    // null. Nessun leak cross-tenant nell'assegnazione.
    Livewire::test(ListAdoptionApplications::class)
        ->callAction(
            TestAction::make('assign')->table($application),
            ['assigned_to' => $outsider->getKey()],
        )
        ->assertHasActionErrors(['assigned_to']);

    expect($application->refresh()->assigned_to)->toBeNull();
});

it('lets staff edit the internal staff notes', function () {
    $orgA = Organization::factory()->verified()->create();
    $application = shelterApplicationFor($orgA);

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(ListAdoptionApplications::class)
        ->callAction(
            TestAction::make('editNotes')->table($application),
            ['staff_notes' => 'Chiamato per fissare il colloquio.'],
        )
        ->assertHasNoActionErrors();

    expect($application->refresh()->staff_notes)->toBe('Chiamato per fissare il colloquio.');
});

it('lets a volunteer view the list but hides the manage actions', function () {
    $orgA = Organization::factory()->verified()->create();
    $application = shelterApplicationFor($orgA);

    // Il volontario ha solo ApplicationView (no ApplicationManage).
    $volunteer = member($orgA, MembershipRole::Volunteer);
    actAsInTenant($volunteer, $orgA);

    Livewire::test(ListAdoptionApplications::class)
        ->assertOk()
        ->assertCanSeeTableRecords([$application])
        ->assertActionHidden(TestAction::make('advance')->table($application))
        ->assertActionHidden(TestAction::make('assign')->table($application))
        ->assertActionHidden(TestAction::make('editNotes')->table($application));
});

it('lets staff view an application of their own org', function () {
    $orgA = Organization::factory()->verified()->create();
    $application = shelterApplicationFor($orgA);

    // Un profilo adottante completo per popolare la sezione privacy-aware.
    AdopterProfile::factory()->complete()->forUser($application->user)->create();

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(ViewAdoptionApplication::class, ['record' => $application->getKey()])
        ->assertOk();
});

it('forbids viewing an application that belongs to another tenant (record binding respects tenancy)', function () {
    $orgA = Organization::factory()->verified()->create();
    $orgB = Organization::factory()->verified()->create();

    $applicationB = shelterApplicationFor($orgB);

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    // Aprire nel tenant A la view di una candidatura di B non la risolve: il
    // record e' fuori dallo scope del tenant corrente (404). E' l'isolamento
    // piu' forte: A non riesce nemmeno a legarla via rotta.
    Livewire::test(ViewAdoptionApplication::class, ['record' => $applicationB->getKey()])
        ->assertNotFound();
});

it('lets staff finalize an AdoptionPending application via the finalize action', function () {
    Notification::fake();
    Storage::fake('local');

    // Fake del generatore PDF (Dependency Inversion): niente motore reale.
    app()->bind(ContractGenerator::class, fn (): ContractGenerator => new class implements ContractGenerator
    {
        public function generate(Adoption $adoption): string
        {
            return '%PDF-1.4 shelter-fake';
        }
    });

    $orgA = Organization::factory()->verified()->create();
    $application = shelterPendingApplicationFor($orgA);

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(ListAdoptionApplications::class)
        ->assertActionVisible(TestAction::make('finalize')->table($application))
        ->callAction(
            TestAction::make('finalize')->table($application),
            ['fee' => '95.00', 'currency' => 'EUR'],
        )
        ->assertHasNoActionErrors()
        ->assertNotified();

    expect($application->refresh()->statusEnum())->toBe(ApplicationStatus::Completed)
        ->and($application->animal->refresh()->status)->toBe(AnimalStatus::Adopted)
        ->and(Adoption::query()->where('application_id', $application->getKey())->exists())->toBeTrue();
})->group('transitions');

it('hides the finalize action when the application is not AdoptionPending', function () {
    $orgA = Organization::factory()->verified()->create();
    // Submitted: la finalizzazione non è offerta.
    $application = shelterApplicationFor($orgA);

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(ListAdoptionApplications::class)
        ->assertActionHidden(TestAction::make('finalize')->table($application));
});

it('hides the finalize action from a volunteer (no ApplicationManage)', function () {
    $orgA = Organization::factory()->verified()->create();
    $application = shelterPendingApplicationFor($orgA);

    $volunteer = member($orgA, MembershipRole::Volunteer);
    actAsInTenant($volunteer, $orgA);

    Livewire::test(ListAdoptionApplications::class)
        ->assertActionHidden(TestAction::make('finalize')->table($application));
});
