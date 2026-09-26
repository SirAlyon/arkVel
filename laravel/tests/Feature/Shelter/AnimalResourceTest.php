<?php

declare(strict_types=1);

use App\Actions\Organizations\AddOrganizationMember;
use App\Enums\AnimalStatus;
use App\Enums\MembershipRole;
use App\Enums\Sex;
use App\Enums\Species;
use App\Filament\Shelter\Resources\Animals\Pages\CreateAnimal;
use App\Filament\Shelter\Resources\Animals\Pages\EditAnimal;
use App\Filament\Shelter\Resources\Animals\Pages\ListAnimals;
use App\Models\Animal;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

afterEach(function () {
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

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

/**
 * Colloca l'utente nel contesto del pannello shelter e del tenant indicato,
 * allineando il team id spatie (come fa il middleware in produzione).
 */
function actAsInTenant(User $user, Organization $tenant): void
{
    test()->actingAs($user);

    $panel = Filament::getPanel('shelter');
    // boot() registra gli observer di tenancy (auto-associazione del tenant in
    // creazione, global scope di scoping). In produzione lo fa il kernel al
    // boot dei pannelli; nei test lo forziamo per deterministicita.
    $panel->boot();

    Filament::setCurrentPanel($panel);
    Filament::setTenant($tenant);

    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($tenant->getKey());
    $registrar->forgetCachedPermissions();
}

it('scopes the list to the current tenant and hides other tenants animals', function () {
    $orgA = Organization::factory()->verified()->create();
    $orgB = Organization::factory()->verified()->create();

    $aAnimals = Animal::factory()->count(3)->forOrganization($orgA)->create();
    $bAnimals = Animal::factory()->count(2)->forOrganization($orgB)->create();

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(ListAnimals::class)
        ->assertOk()
        ->assertCanSeeTableRecords($aAnimals)
        ->assertCanNotSeeTableRecords($bAnimals);
});

it('forbids editing an animal that belongs to another tenant (cross-tenant isolation)', function () {
    $orgA = Organization::factory()->verified()->create();
    $orgB = Organization::factory()->verified()->create();

    $animalB = Animal::factory()->forOrganization($orgB)->create();

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    // Montare la Edit page di un record di B nel tenant A deve fallire: il
    // record e' fuori dallo scope del tenant corrente, quindi il binding di
    // rotta non lo trova (404). E' l'isolamento piu' forte: A non solo non puo'
    // editarlo, non riesce nemmeno a risolverlo. (La Policy negherebbe comunque
    // via isVerifiedMemberOf, cfr. AnimalPolicyTest.)
    Livewire::test(EditAnimal::class, ['record' => $animalB->getKey()])
        ->assertNotFound();
});

it('creates an animal auto-associated to the current tenant with default Draft status', function () {
    $orgA = Organization::factory()->verified()->create();

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(CreateAnimal::class)
        ->assertOk()
        ->fillForm([
            'name' => 'Fido',
            'species' => Species::Dog->value,
            'sex' => Sex::Male->value,
            'status' => AnimalStatus::Draft->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $animal = Animal::query()->where('name', 'Fido')->sole();

    expect($animal->organization_id)->toBe($orgA->getKey())
        ->and($animal->status)->toBe(AnimalStatus::Draft);
});

it('lets staff choose Available as the initial status on create', function () {
    $orgA = Organization::factory()->verified()->create();
    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(CreateAnimal::class)
        ->fillForm([
            'name' => 'Luna',
            'species' => Species::Cat->value,
            'sex' => Sex::Female->value,
            'status' => AnimalStatus::Available->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Animal::query()->where('name', 'Luna')->sole()->status)
        ->toBe(AnimalStatus::Available);
});

it('requires a name and species when creating', function () {
    $orgA = Organization::factory()->verified()->create();
    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(CreateAnimal::class)
        ->fillForm([
            'name' => null,
            'species' => null,
            'sex' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['name', 'species', 'sex']);
});

it('requires a pause reason when applications are paused', function () {
    $orgA = Organization::factory()->verified()->create();
    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(CreateAnimal::class)
        ->fillForm([
            'name' => 'Argo',
            'species' => Species::Dog->value,
            'sex' => Sex::Male->value,
            'status' => AnimalStatus::Draft->value,
            'application_paused' => true,
            'pause_reason' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['pause_reason']);
});

it('rejects a duplicate microchip within the same tenant', function () {
    $orgA = Organization::factory()->verified()->create();
    Animal::factory()->forOrganization($orgA)->create(['microchip_number' => '111222333444555']);

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(CreateAnimal::class)
        ->fillForm([
            'name' => 'Zeus',
            'species' => Species::Dog->value,
            'sex' => Sex::Male->value,
            'status' => AnimalStatus::Draft->value,
            'microchip_number' => '111222333444555',
        ])
        ->call('create')
        ->assertHasFormErrors(['microchip_number']);
});

it('lets a volunteer view the list but not create animals', function () {
    $orgA = Organization::factory()->verified()->create();
    $animals = Animal::factory()->count(2)->forOrganization($orgA)->create();

    $volunteer = member($orgA, MembershipRole::Volunteer);
    actAsInTenant($volunteer, $orgA);

    // Vede la lista...
    Livewire::test(ListAnimals::class)
        ->assertOk()
        ->assertCanSeeTableRecords($animals)
        ->assertActionHidden('create');

    // ...ma non puo' montare la Create page (Policy create nega).
    Livewire::test(CreateAnimal::class)->assertForbidden();
});

it('forbids a volunteer from editing an animal', function () {
    $orgA = Organization::factory()->verified()->create();
    $animal = Animal::factory()->forOrganization($orgA)->create();

    $volunteer = member($orgA, MembershipRole::Volunteer);
    actAsInTenant($volunteer, $orgA);

    Livewire::test(EditAnimal::class, ['record' => $animal->getKey()])
        ->assertForbidden();
});

it('offers only valid transitions and applies the chosen one via the Action', function () {
    $orgA = Organization::factory()->verified()->create();
    $animal = Animal::factory()->draft()->forOrganization($orgA)->create();

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    // Draft -> {Available, NotAvailable}. Applichiamo Available.
    Livewire::test(ListAnimals::class)
        ->assertActionVisible(TestAction::make('changeStatus')->table($animal))
        ->callAction(
            TestAction::make('changeStatus')->table($animal),
            ['status' => AnimalStatus::Available->value],
        )
        ->assertHasNoActionErrors();

    expect($animal->refresh()->status)->toBe(AnimalStatus::Available);

    // La transizione ha loggato via activitylog (delegato all'Action).
    expect(Activity::query()->where('description', 'animal.status_changed')->count())
        ->toBe(1);
});

it('rejects an invalid transition target that is not offered', function () {
    $orgA = Organization::factory()->verified()->create();
    $animal = Animal::factory()->draft()->forOrganization($orgA)->create();

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    // Adopted non e' una transizione ammessa da Draft: il Select la rifiuta.
    Livewire::test(ListAnimals::class)
        ->callAction(
            TestAction::make('changeStatus')->table($animal),
            ['status' => AnimalStatus::Adopted->value],
        )
        ->assertHasActionErrors(['status']);

    expect($animal->refresh()->status)->toBe(AnimalStatus::Draft);
});

it('hides the change status action for a terminal Adopted animal', function () {
    $orgA = Organization::factory()->verified()->create();
    $animal = Animal::factory()->adopted()->forOrganization($orgA)->create();

    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(ListAnimals::class)
        ->assertActionHidden(TestAction::make('changeStatus')->table($animal));
});

it('attaches an uploaded photo to the photos collection on create', function () {
    Storage::fake('public');

    $orgA = Organization::factory()->verified()->create();
    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(CreateAnimal::class)
        ->fillForm([
            'name' => 'Nina',
            'species' => Species::Cat->value,
            'sex' => Sex::Female->value,
            'status' => AnimalStatus::Draft->value,
            'photos' => [UploadedFile::fake()->image('nina.jpg', 400, 400)],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $animal = Animal::query()->where('name', 'Nina')->sole();

    expect($animal->getMedia('photos'))->toHaveCount(1);
});

it('persists the multilingual description on both locales', function () {
    $orgA = Organization::factory()->verified()->create();
    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(CreateAnimal::class)
        ->fillForm([
            'name' => 'Leo',
            'species' => Species::Dog->value,
            'sex' => Sex::Male->value,
            'status' => AnimalStatus::Draft->value,
            'description.it' => '<p>Cane socievole</p>',
            'description.en' => '<p>Friendly dog</p>',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $animal = Animal::query()->where('name', 'Leo')->sole();

    expect($animal->getTranslation('description', 'it'))->toContain('socievole')
        ->and($animal->getTranslation('description', 'en'))->toContain('Friendly');
});

it('editing one locale of the description preserves the other locale', function () {
    $orgA = Organization::factory()->verified()->create();
    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    $animal = Animal::factory()->forOrganization($orgA)->create([
        'description' => ['it' => 'Testo IT', 'en' => 'Text EN'],
    ]);

    // Modifichiamo SOLO description.it: la persistenza JSON multilingua non deve
    // perdere ne' sovrascrivere l'altro locale (nessun leak tra locali).
    Livewire::test(EditAnimal::class, ['record' => $animal->getKey()])
        ->assertOk()
        ->fillForm([
            'description.it' => 'Testo IT aggiornato',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $animal->refresh();

    // Il RichEditor normalizza il markup sul round-trip (incapsula in <p>...</p>),
    // quindi confrontiamo il contenuto testuale, non il markup grezzo. Cruciale:
    // il locale EN NON viene perso ne' contaminato dal testo IT (no leak tra
    // locali) e il locale IT riflette la modifica.
    expect($animal->getTranslation('description', 'it'))->toContain('Testo IT aggiornato')
        ->and($animal->getTranslation('description', 'it'))->not->toContain('Text EN')
        ->and($animal->getTranslation('description', 'en'))->toContain('Text EN')
        ->and($animal->getTranslation('description', 'en'))->not->toContain('Testo IT');
});

it('keeps a single file in the avatar collection replacing the previous one', function () {
    Storage::fake('public');

    $orgA = Organization::factory()->verified()->create();
    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    // Primo upload sulla collection avatar (singleFile).
    Livewire::test(CreateAnimal::class)
        ->fillForm([
            'name' => 'Milo',
            'species' => Species::Cat->value,
            'sex' => Sex::Male->value,
            'status' => AnimalStatus::Draft->value,
            'avatar' => [UploadedFile::fake()->image('first.jpg', 400, 400)],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $animal = Animal::query()->where('name', 'Milo')->sole();

    expect($animal->getMedia('avatar'))->toHaveCount(1);

    $firstMediaId = $animal->getFirstMedia('avatar')?->getKey();

    // Caricando una seconda immagine sulla stessa collection, la semantica
    // singleFile sostituisce la precedente: il conteggio resta 1.
    Livewire::test(EditAnimal::class, ['record' => $animal->getKey()])
        ->assertOk()
        ->fillForm([
            'avatar' => [UploadedFile::fake()->image('second.jpg', 400, 400)],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $animal->refresh();

    expect($animal->getMedia('avatar'))->toHaveCount(1)
        ->and($animal->getFirstMedia('avatar')?->getKey())->not->toBe($firstMediaId);
});

it('lets staff restore and force-delete a trashed animal but hides the actions from a volunteer', function () {
    $orgA = Organization::factory()->verified()->create();
    $animal = Animal::factory()->forOrganization($orgA)->create();
    $animal->delete();

    expect($animal->trashed())->toBeTrue();

    // Lo staff (animal.delete) e' autorizzato a restore/forceDelete nel proprio
    // ente: le azioni sono visibili nella table (TrashedFilter mostra i cestinati).
    $staff = member($orgA, MembershipRole::Staff);
    actAsInTenant($staff, $orgA);

    Livewire::test(ListAnimals::class, [
        'tableFilters' => ['trashed' => ['value' => '0']],
    ])
        ->assertOk()
        ->assertCanSeeTableRecords([$animal])
        ->assertActionVisible(TestAction::make('restore')->table($animal))
        ->assertActionVisible(TestAction::make('forceDelete')->table($animal));

    // Reset del team id spatie prima di cambiare attore (evita cache stantia).
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);

    // Il volontario (solo animal.view) NON e' autorizzato: le azioni di
    // restore/forceDelete sono nascoste (Policy nega).
    $volunteer = member($orgA, MembershipRole::Volunteer);
    actAsInTenant($volunteer, $orgA);

    Livewire::test(ListAnimals::class, [
        'tableFilters' => ['trashed' => ['value' => '0']],
    ])
        ->assertOk()
        ->assertCanSeeTableRecords([$animal])
        ->assertActionHidden(TestAction::make('restore')->table($animal))
        ->assertActionHidden(TestAction::make('forceDelete')->table($animal));
});
