<?php

declare(strict_types=1);

use App\Enums\HousingType;
use App\Enums\RentOrOwn;
use App\Enums\RoleName;
use App\Filament\Adopter\Pages\ManageAdopterProfile;
use App\Http\Middleware\EnsureAdopterRole;
use App\Models\AdopterProfile;
use App\Models\Animal;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    // Il pannello adopter deve essere quello corrente perché le pagine risolvano
    // il broker/model utente e la config del pannello (come per gli altri test).
    Filament::setCurrentPanel(Filament::getPanel('adopter'));
});

afterEach(function () {
    app(PermissionRegistrar::class)->setPermissionsTeamId(null);
});

/**
 * Il pannello adopter (per risolvere il panel id in canAccessPanel).
 */
function adopterPanel(): Panel
{
    return Filament::getPanel('adopter');
}

/**
 * L'utente ha il ruolo Adopter, rilevato TEAM-INDEPENDENT (query diretta sul
 * pivot come EnsureAdopterRole/isSuperAdmin): non dipende dal team corrente.
 */
function adopterRoleAssigned(User $user): bool
{
    return DB::table('model_has_roles')
        ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
        ->where('model_has_roles.model_id', $user->getKey())
        ->where('model_has_roles.model_type', $user->getMorphClass())
        ->where('roles.name', RoleName::Adopter->value)
        ->exists();
}

/**
 * Numero di assegnazioni del ruolo Adopter all'utente (team-independent), per
 * verificare l'idempotenza dell'assegnazione.
 */
function adopterRoleAssignmentsCount(User $user): int
{
    return DB::table('model_has_roles')
        ->join('roles', 'model_has_roles.role_id', '=', 'roles.id')
        ->where('model_has_roles.model_id', $user->getKey())
        ->where('model_has_roles.model_type', $user->getMorphClass())
        ->where('roles.name', RoleName::Adopter->value)
        ->count();
}

// --- accesso al pannello --------------------------------------------------------

it('lets a verified user access the adopter panel shell', function () {
    $user = User::factory()->create();

    expect($user->hasVerifiedEmail())->toBeTrue()
        ->and($user->canAccessPanel(adopterPanel()))->toBeTrue();
});

it('lets a verified user reach the profile page', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(ManageAdopterProfile::class)
        ->assertOk();
});

it('redirects an unverified user to the email verification prompt', function () {
    $user = User::factory()->unverified()->create();

    // La verifica email è OBBLIGATORIA sul pannello adopter: il middleware
    // `verified` devia le rotte PROTETTE verso la prompt di verifica. La radice
    // /account è solo un redirect alla pagina profilo (la rotta protetta), quindi
    // asseriamo la protezione direttamente sulla pagina.
    $this->actingAs($user)
        ->get(route('filament.adopter.pages.manage-adopter-profile'))
        ->assertRedirect(route('filament.adopter.auth.email-verification.prompt'));
});

it('redirects a guest visiting the adopter panel to its login', function () {
    $this->get('/account')
        ->assertRedirect(route('filament.adopter.auth.login'));
});

// --- assegnazione del ruolo Adopter (team-independent) --------------------------

it('assigns the Adopter role team-independently on an adopter panel request', function () {
    $user = User::factory()->create();

    // Precondizione: nessun ruolo Adopter ancora.
    expect($user->hasRole(RoleName::Adopter->value))->toBeFalse();

    $middleware = app(EnsureAdopterRole::class);
    $this->actingAs($user);

    $response = $middleware->handle(Request::create('/account'), fn (): Response => new Response);

    expect($response)->toBeInstanceOf(Response::class);

    // Il ruolo è assegnato sotto il team sentinel di piattaforma (il rilevamento è
    // team-independent). Verifichiamo l'assegnazione con una query diretta sul
    // pivot, indipendente dal team corrente, e via hasRole sotto il sentinel.
    expect(adopterRoleAssigned($user))->toBeTrue();

    app(PermissionRegistrar::class)->setPermissionsTeamId(RoleName::PLATFORM_TEAM_ID);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    expect($user->fresh()->hasRole(RoleName::Adopter->value))->toBeTrue();
});

it('is idempotent: assigning the Adopter role twice keeps a single assignment', function () {
    $user = User::factory()->create();
    $middleware = app(EnsureAdopterRole::class);
    $this->actingAs($user);

    $middleware->handle(Request::create('/account'), fn (): Response => new Response);
    $middleware->handle(Request::create('/account'), fn (): Response => new Response);

    expect(adopterRoleAssignmentsCount($user))->toBe(1);
});

it('detects the Adopter role regardless of the current team id (no double assignment)', function () {
    // Il rilevamento è team-independent: se un team id residuo di un ente fosse
    // attivo, un rilevamento team-scoped mascherebbe l'assegnazione e ne creerebbe
    // una seconda. Verifichiamo che ciò NON accada.
    $user = User::factory()->create();
    $middleware = app(EnsureAdopterRole::class);
    $this->actingAs($user);

    $middleware->handle(Request::create('/account'), fn (): Response => new Response);

    // Simuliamo un team id residuo di un ente estraneo prima della seconda passata.
    $strangerTeamId = Organization::factory()->verified()->create()->getKey();
    app(PermissionRegistrar::class)->setPermissionsTeamId($strangerTeamId);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $middleware->handle(Request::create('/account'), fn (): Response => new Response);

    expect(adopterRoleAssignmentsCount($user))->toBe(1);
});

// --- isolamento: ownership + niente tenancy -------------------------------------

it('gives a pure adopter no tenants on the shelter panel', function () {
    // Un adottante puro (nessuna membership verificata) non ha alcun tenant
    // selezionabile sul pannello shelter: niente accesso a dati d'ente.
    $adopter = User::factory()->create();

    expect($adopter->getTenants(Filament::getPanel('shelter'))->all())->toBe([]);
});

it('keeps a pure adopter out of an organizations shelter resources (no tenancy)', function () {
    // Un adottante puro non è membro verificato di alcun ente: non può operare le
    // risorse d'ente. Anche forzando il contesto tenant di un ente altrui, la
    // Policy (isVerifiedMemberOf) nega e lo scoping impedisce ogni fuga.
    $organization = Organization::factory()->verified()->create();
    $animal = Animal::factory()->forOrganization($organization)->create();
    $adopter = User::factory()->create();

    expect($adopter->canAccessTenant($organization))->toBeFalse()
        ->and($adopter->can('view', $animal))->toBeFalse()
        ->and($adopter->can('update', $animal))->toBeFalse();
});

it('denies an adopter viewing or updating another users profile (Policy ownership)', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $profile = AdopterProfile::factory()->forUser($owner)->create();

    // L'isolamento del pannello adopter è per OWNERSHIP (AdopterProfilePolicy),
    // non per tenancy: un altro utente non può vedere né modificare il profilo.
    expect($intruder->can('view', $profile))->toBeFalse()
        ->and($intruder->can('update', $profile))->toBeFalse()
        ->and($owner->can('view', $profile))->toBeTrue()
        ->and($owner->can('update', $profile))->toBeTrue();
});

// --- salvataggio del profilo, consenso e validazione condizionale ---------------

/**
 * Dati validi e COMPLETI del questionario (proprietà, nessun giardino), pronti da
 * combinare con il consenso GDPR nei test di salvataggio.
 *
 * @return array<string, mixed>
 */
function completeQuestionnaire(): array
{
    return [
        'housing_type' => HousingType::House->value,
        'rent_or_own' => RentOrOwn::Own->value,
        'has_garden' => false,
        'adults_in_home' => 2,
        'hours_alone_weekday' => 4,
        'contingency_plan' => 'Se ne occuperà mia sorella.',
    ];
}

it('creates the profile and captures consented_at when consent is given', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(ManageAdopterProfile::class)
        ->fillForm([
            ...completeQuestionnaire(),
            'consent_data_processing' => true,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $profile = AdopterProfile::query()->where('user_id', $user->getKey())->sole();

    expect($profile->consent_data_processing)->toBeTrue()
        ->and($profile->consented_at)->not->toBeNull()
        ->and($profile->isComplete())->toBeTrue();
});

it('preserves the original consented_at on a subsequent save', function () {
    $user = User::factory()->create();
    $original = now()->subDays(3);
    $profile = AdopterProfile::factory()->forUser($user)->complete()->create([
        'consented_at' => $original,
    ]);

    Livewire::actingAs($user)
        ->test(ManageAdopterProfile::class)
        ->fillForm([
            ...completeQuestionnaire(),
            'consent_data_processing' => true,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    // Il timestamp originale del consenso NON viene riscritto su un nuovo salvataggio.
    expect($profile->fresh()->consented_at->timestamp)->toBe($original->timestamp);
});

it('blocks saving without the data processing consent', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(ManageAdopterProfile::class)
        ->fillForm([
            ...completeQuestionnaire(),
            'consent_data_processing' => false,
        ])
        ->call('save')
        ->assertHasFormErrors(['consent_data_processing']);

    expect(AdopterProfile::query()->where('user_id', $user->getKey())->exists())->toBeFalse();
});

it('requires landlord consent when renting (conditional validation)', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(ManageAdopterProfile::class)
        ->fillForm([
            ...completeQuestionnaire(),
            'rent_or_own' => RentOrOwn::Rent->value,
            'landlord_allows_pets' => false,
            'consent_data_processing' => true,
        ])
        ->call('save')
        ->assertHasFormErrors(['landlord_allows_pets']);
});

it('accepts renting when the landlord allows pets', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(ManageAdopterProfile::class)
        ->fillForm([
            ...completeQuestionnaire(),
            'rent_or_own' => RentOrOwn::Rent->value,
            'landlord_allows_pets' => true,
            'consent_data_processing' => true,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $profile = AdopterProfile::query()->where('user_id', $user->getKey())->sole();

    expect($profile->rent_or_own)->toBe(RentOrOwn::Rent)
        ->and($profile->landlord_allows_pets)->toBeTrue()
        ->and($profile->isComplete())->toBeTrue();
});

it('requires the fenced answer when a garden is present (conditional validation)', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(ManageAdopterProfile::class)
        ->fillForm([
            ...completeQuestionnaire(),
            'has_garden' => true,
            'fenced' => null,
            'consent_data_processing' => true,
        ])
        ->call('save')
        ->assertHasFormErrors(['fenced']);
});

it('keeps isComplete false for an incomplete questionnaire even with consent', function () {
    $user = User::factory()->create();

    // Manca contingency_plan (campo obbligatorio del dominio): con il consenso il
    // form supera la validazione GDPR ma il profilo resta incompleto per il dominio.
    Livewire::actingAs($user)
        ->test(ManageAdopterProfile::class)
        ->fillForm([
            'housing_type' => HousingType::Apartment->value,
            'rent_or_own' => RentOrOwn::Own->value,
            'has_garden' => false,
            'adults_in_home' => 1,
            'hours_alone_weekday' => 6,
            'contingency_plan' => null,
            'consent_data_processing' => true,
        ])
        ->call('save')
        ->assertHasFormErrors(['contingency_plan']);

    expect(AdopterProfile::query()->where('user_id', $user->getKey())->exists())->toBeFalse();
});
