<?php

declare(strict_types=1);

use App\Enums\AnimalStatus;
use App\Enums\AppointmentStatus;
use App\Enums\FosterApplicationStatus;
use App\Enums\FosterPlacementStatus;
use App\Enums\MembershipRole;
use App\Enums\OrganizationStatus;
use App\Enums\PermissionName;
use App\Enums\RoleName;
use App\Models\Animal;
use App\Models\Appointment;
use App\Models\FosterApplication;
use App\Models\FosterPlacement;
use App\Models\MedicalRecord;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Le foto placeholder finiscono su un disk finto: nessuna scrittura reale
    // e nessuna conversione su disco durante i test.
    Storage::fake('public');

    // Nessuna chiamata di rete reale nei test: la sorgente foto (LoremFlickr) è
    // stubbata con un JPEG 1x1 valido, così esercitiamo il PERCORSO REALE del
    // fetcher (foto scaricate) in modo deterministico e offline.
    Http::preventStrayRequests();
    Http::fake([
        // Sorgenti foto per-specie del fetcher (placedog/cataas/picsum/loremflickr):
        // stubbate con un JPEG 1x1 valido e content-type image/jpeg accettato.
        '*' => Http::response(onePixelJpeg(), 200, [
            'Content-Type' => 'image/jpeg',
        ]),
    ]);

    // Esegue l'intero orchestratore (ruoli + dati demo) come in produzione.
    $this->seed(DatabaseSeeder::class);
});

/**
 * Byte di un JPEG 1x1 valido (medialibrary deduce il mime dal contenuto).
 */
function onePixelJpeg(): string
{
    return base64_decode(
        '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRof'.
        'Hh0aHBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAALCAABAAEBAREA/8QAFAAB'.
        'AAAAAAAAAAAAAAAAAAAACP/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AfwD/2Q==',
        true,
    ) ?: '';
}

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

it('attaches real photos (and an avatar) to every seeded animal', function () {
    Animal::query()->each(function (Animal $animal): void {
        // Con la sorgente foto stubbata, ogni animale deve avere sia foto sia
        // avatar (percorso reale del fetcher, non il fallback offline GD).
        expect($animal->getMedia('photos')->count())
            ->toBeGreaterThan(0, "L'animale {$animal->slug} non ha foto")
            ->and($animal->getMedia('avatar')->count())
            ->toBe(1, "L'animale {$animal->slug} non ha avatar");
    });

    // Regressione: le sorgenti foto REMOTE per-specie sono state effettivamente
    // interrogate (percorso reale del fetcher), non solo il fallback offline GD.
    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'placedog.net')
        || str_contains($request->url(), 'cataas.com')
        || str_contains($request->url(), 'picsum.photos'));
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

it('creates demo adopters with a complete adopter profile', function () {
    $adopter = User::where('email', 'adopter+1@arkvel.test')->first();

    expect($adopter)->not->toBeNull()
        ->and($adopter->email_verified_at)->not->toBeNull()
        ->and($adopter->hasCompleteAdopterProfile())->toBeTrue();

    // Il ruolo Adopter (di piattaforma) è rilevato team-independent, come in
    // EnsureAdopterRole: verifichiamo l'assegnazione col team sentinel.
    seederWithTeam(RoleName::PLATFORM_TEAM_ID);
    expect($adopter->fresh()->hasRole(RoleName::Adopter->value))->toBeTrue();
});

it('seeds foster applications across multiple states', function () {
    $states = FosterApplication::query()
        ->get()
        ->map(fn (FosterApplication $application): FosterApplicationStatus => $application->statusEnum())
        ->unique();

    expect(FosterApplication::count())->toBeGreaterThan(0)
        ->and($states->count())->toBeGreaterThanOrEqual(3);
});

it('seeds an active foster coupled to a Fostered animal with an active placement', function () {
    $activePlacement = FosterPlacement::query()
        ->where('status', FosterPlacementStatus::Active)
        ->with(['animal', 'fosterApplication'])
        ->first();

    expect($activePlacement)->not->toBeNull()
        ->and($activePlacement->animal->status)->toBe(AnimalStatus::Fostered)
        ->and($activePlacement->fosterApplication)->not->toBeNull()
        ->and($activePlacement->fosterApplication->statusEnum())->toBe(FosterApplicationStatus::Active);
});

it('seeds a completed foster with an ended placement and the animal back Available', function () {
    $endedPlacement = FosterPlacement::query()
        ->where('status', FosterPlacementStatus::Ended)
        ->with(['fosterApplication', 'animal'])
        ->first();

    expect($endedPlacement)->not->toBeNull()
        ->and($endedPlacement->end_date)->not->toBeNull()
        ->and($endedPlacement->fosterApplication?->statusEnum())->toBe(FosterApplicationStatus::Completed)
        // Rientro: l'animale del placement concluso NON resta bloccato in Fostered.
        ->and($endedPlacement->animal->status)->not->toBe(AnimalStatus::Fostered);
});

it('never reuses the same (animal, carer) pair across foster applications', function () {
    // Il seeder assegna un animale distinto per richiesta: il vincolo unique
    // (animal_id, user_id) non deve mai essere a rischio.
    $pairs = FosterApplication::query()
        ->get()
        ->map(fn (FosterApplication $application): string => $application->animal_id.'-'.$application->user_id);

    expect($pairs->count())->toBe($pairs->unique()->count());
});

it('seeds appointments across types and states', function () {
    $statuses = Appointment::query()
        ->get()
        ->pluck('status')
        ->unique();

    expect(Appointment::count())->toBeGreaterThan(0)
        ->and($statuses->count())->toBeGreaterThanOrEqual(3);
});

it('seeds at least one confirmed appointment due for a reminder', function () {
    // Un appuntamento confermato entro 24h con reminder_sent_at null: il comando
    // arkvel:send-appointment-reminders deve avere qualcosa da inviare nel demo.
    expect(Appointment::query()->dueForReminder(24)->exists())->toBeTrue();

    $due = Appointment::query()->dueForReminder(24)->first();
    expect($due?->status)->toBe(AppointmentStatus::Confirmed);
});

it('seeds medical records due for a reminder', function () {
    // Voci con next_due_at nella finestra e reminder_sent_at null: lo scadenzario
    // e arkvel:send-medical-reminders devono avere dati dimostrabili.
    expect(MedicalRecord::query()->dueForReminder(7)->exists())->toBeTrue();
});
