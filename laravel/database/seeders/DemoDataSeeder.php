<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Organizations\AddOrganizationMember;
use App\Enums\AnimalStatus;
use App\Enums\MedicalRecordType;
use App\Enums\MembershipRole;
use App\Enums\OrganizationType;
use App\Enums\RoleName;
use App\Enums\Species;
use App\Models\Animal;
use App\Models\MedicalRecord;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\Support\PlaceholderImageFactory;
use Database\Seeders\Support\RemoteAnimalPhotoFetcher;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

/**
 * Popola un dataset dimostrativo REALISTICO (Faker it_IT):
 *
 *   - 1 Super Admin a credenziali note (login demo del pannello admin);
 *   - 3 enti verificati, ciascuno con Owner/Staff/Volunteer a credenziali note;
 *   - 6–10 animali per ente con mix di specie/stato/comportamento realistico,
 *     ognuno con 1 avatar + 1–3 foto REALI species-appropriate (cani da
 *     placedog.net, gatti da cataas.com, altri da picsum; vedi
 *     {@see RemoteAnimalPhotoFetcher}); se la rete non è disponibile si ricade su
 *     placeholder GD offline, senza mai abortire il seeding;
 *   - alcune cartelle sanitarie coerenti per una parte degli animali.
 *
 * Idempotenza: gli utenti a credenziali note usano firstOrCreate (rieseguibile
 * senza errori); enti/animali sono pensati per `migrate:fresh --seed` (DB pulito).
 *
 * Assegnazione ruoli: SEMPRE delegata all'Action AddOrganizationMember, che
 * imposta il team id dell'ente e assegna il ruolo spatie team-scoped. Il seeder
 * non tocca mai a mano pivot o model_has_roles.
 */
class DemoDataSeeder extends Seeder
{
    private const string DEMO_PASSWORD = 'password';

    private const string SUPER_ADMIN_EMAIL = 'admin@arkvel.test';

    /**
     * Team sentinel per i ruoli di piattaforma (non org-scoped): unica fonte di
     * verità su RoleName. Neutro, non coincide con nessuna Organization reale (le
     * PK partono da 1); irrilevante perché il rilevamento ignora il team.
     */
    private const int PLATFORM_TEAM_ID = RoleName::PLATFORM_TEAM_ID;

    /**
     * Definizione dei 3 enti demo: chiave slug corta (per le email staff),
     * nome, tipo. I dati anagrafici restanti arrivano dalla factory verified().
     *
     * @var list<array{key: string, name: string, type: OrganizationType}>
     */
    private const array ORGANIZATIONS = [
        [
            'key' => 'milano',
            'name' => 'Canile Comunale di Milano',
            'type' => OrganizationType::CanileComunale,
        ],
        [
            'key' => 'roma',
            'name' => 'Rifugio Amici a Quattro Zampe Roma',
            'type' => OrganizationType::Rifugio,
        ],
        [
            'key' => 'torino',
            'name' => 'Associazione Zampe Felici Torino',
            'type' => OrganizationType::Associazione,
        ],
    ];

    private Generator $faker;

    public function __construct(
        private readonly AddOrganizationMember $addOrganizationMember,
        private readonly PlaceholderImageFactory $placeholderImages,
        private readonly RemoteAnimalPhotoFetcher $remotePhotos,
        private readonly PermissionRegistrar $permissionRegistrar,
    ) {
        $this->faker = fake('it_IT');
    }

    public function run(): void
    {
        // Difesa in profondità (oltre alla guardia in DatabaseSeeder): i dati
        // demo includono un Super Admin a password nota e PII fittizia, che non
        // devono MAI essere creati in produzione (privacy/GDPR by design).
        if (app()->isProduction()) {
            $this->command?->warn(
                'DemoDataSeeder saltato: i dati demo non vengono seminati in produzione.',
            );

            return;
        }

        $superAdmin = $this->createSuperAdmin();

        $firstOwnerEmail = null;

        foreach (self::ORGANIZATIONS as $definition) {
            [$organization, $isNew] = $this->resolveOrganization($definition);

            $ownerEmail = $this->createStaff($organization, $definition['key']);
            $firstOwnerEmail ??= $ownerEmail;

            // Gli animali si seminano solo per gli enti appena creati: su un
            // DB non fresco (re-run di db:seed) evitiamo di gonfiare il catalogo
            // con duplicati. Il target è comunque `migrate:fresh --seed`.
            if ($isNew) {
                $this->createAnimals($organization);
            }
        }

        $this->printSummary($superAdmin->email, $firstOwnerEmail);
    }

    /**
     * Super Admin a credenziali note: firstOrCreate idempotente sull'email.
     */
    private function createSuperAdmin(): User
    {
        $user = User::firstOrCreate(
            ['email' => self::SUPER_ADMIN_EMAIL],
            [
                'name' => 'ArkVel Super Admin',
                'password' => Hash::make(self::DEMO_PASSWORD),
                'email_verified_at' => now(),
            ],
        );

        // Il ruolo Super Admin è di piattaforma (non org-scoped). In modalità
        // teams la colonna model_has_roles.team_id è NOT NULL (parte della PK):
        // non esiste un'assegnazione "senza team". Il team è però IRRILEVANTE
        // per il Super Admin, perché sia il bypass Gate::before
        // (AppServiceProvider::isSuperAdmin) sia User::isSuperAdmin rilevano il
        // ruolo ignorando il team. Usiamo quindi un sentinel neutro (0) che non
        // coincide con nessun ente reale (le PK organizations partono da 1).
        $previousTeamId = $this->permissionRegistrar->getPermissionsTeamId();
        $this->permissionRegistrar->setPermissionsTeamId(self::PLATFORM_TEAM_ID);

        try {
            if (! $user->hasRole(RoleName::SuperAdmin->value)) {
                $user->assignRole(RoleName::SuperAdmin->value);
            }
        } finally {
            $this->permissionRegistrar->setPermissionsTeamId($previousTeamId);
        }

        return $user;
    }

    /**
     * Risolve l'ente per slug deterministico: lo riusa se esiste già (re-run su
     * DB non fresco), altrimenti lo crea con la factory verified(). Ritorna anche
     * un flag che indica se è stato appena creato (per decidere se seminare gli
     * animali). Lo slug fisso garantisce idempotenza sul vincolo unique.
     *
     * @param  array{key: string, name: string, type: OrganizationType}  $definition
     * @return array{0: Organization, 1: bool}
     */
    private function resolveOrganization(array $definition): array
    {
        $slug = Str::slug($definition['name']);

        $existing = Organization::query()->where('slug', $slug)->first();

        if ($existing !== null) {
            return [$existing, false];
        }

        $organization = Organization::factory()
            ->verified()
            ->create([
                'name' => $definition['name'],
                'slug' => $slug,
                'type' => $definition['type'],
            ]);

        return [$organization, true];
    }

    /**
     * Crea Owner/Staff/Volunteer a credenziali note e li aggiunge come membri
     * VERIFICATI tramite l'Action (che assegna il ruolo spatie team-scoped).
     *
     * @return string Email dell'Owner (usata nel riepilogo di console).
     */
    private function createStaff(Organization $organization, string $key): string
    {
        $ownerEmail = "owner+{$key}@arkvel.test";

        $this->addMember($organization, $ownerEmail, 'Titolare '.$organization->name, MembershipRole::Owner);
        $this->addMember($organization, "staff+{$key}@arkvel.test", 'Staff '.$organization->name, MembershipRole::Staff);
        $this->addMember($organization, "volunteer+{$key}@arkvel.test", 'Volontario '.$organization->name, MembershipRole::Volunteer);

        return $ownerEmail;
    }

    private function addMember(
        Organization $organization,
        string $email,
        string $name,
        MembershipRole $role,
    ): void {
        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make(self::DEMO_PASSWORD),
                'email_verified_at' => now(),
            ],
        );

        $this->addOrganizationMember->handle(
            organization: $organization,
            user: $user,
            role: $role,
            verified: true,
        );
    }

    /**
     * 6–10 animali per ente con mix realistico di specie/stato e foto reali
     * species-appropriate (fallback offline se la rete non è disponibile).
     */
    private function createAnimals(Organization $organization): void
    {
        $count = $this->faker->numberBetween(6, 10);

        for ($i = 0; $i < $count; $i++) {
            $animal = Animal::factory()
                ->forOrganization($organization)
                ->state($this->animalState($i))
                ->create();

            $this->attachPhotos($animal, $i);
            $this->attachMedicalRecords($animal);
        }
    }

    /**
     * Stato coerente per l'i-esimo animale: prevalenza Available, con qualche
     * Draft/Adopted/MedicalHold e specie a prevalenza cani/gatti. Deterministico
     * rispetto all'indice per garantire sempre un mix (non solo casuale).
     *
     * @return array<string, mixed>
     */
    private function animalState(int $index): array
    {
        $status = match ($index % 6) {
            0 => AnimalStatus::Draft,
            1 => AnimalStatus::Adopted,
            2 => AnimalStatus::MedicalHold,
            default => AnimalStatus::Available,
        };

        $species = match ($index % 5) {
            0, 1 => Species::Dog,
            2, 3 => Species::Cat,
            default => $this->faker->randomElement(Species::cases()),
        };

        return [
            'status' => $status,
            'species' => $species,
            // Un paio di animali disponibili messi in evidenza.
            'featured' => $status === AnimalStatus::Available && $index % 3 === 0,
        ];
    }

    /**
     * Attacca 1–3 foto REALI (species-appropriate) all'animale, con la prima
     * anche come avatar, così il catalogo demo risulta credibile.
     *
     * Idempotenza: se l'animale ha GIÀ foto (collection `photos`), non fa nulla —
     * un re-run non duplica le immagini.
     *
     * Sorgente: {@see RemoteAnimalPhotoFetcher} (per-specie: cani placedog.net,
     * gatti cataas.com, altri picsum; URL deterministico per specie/id). Ogni
     * download e ogni attach sono resilienti: se falliscono (offline/timeout/file
     * corrotto) si logga e si prosegue. Se NESSUNA foto reale è stata attaccata,
     * ricadiamo sul placeholder GD offline così l'animale ha comunque almeno
     * un'immagine (e un avatar) anche senza rete.
     */
    private function attachPhotos(Animal $animal, int $paletteSeed): void
    {
        // Idempotente: mai duplicare le foto su un re-run del seeder.
        if ($animal->hasMedia('photos')) {
            return;
        }

        $photoCount = $this->faker->numberBetween(1, 3);
        $attached = 0;

        for ($variant = 0; $variant < $photoCount; $variant++) {
            $path = $this->remotePhotos->fetch($animal->species, $animal->getKey(), $variant);

            if ($path === null) {
                continue; // download fallito (già loggato): salta questo slot.
            }

            // Attach resiliente: se il file remoto è corrotto/inutilizzabile
            // (attach fallito, già loggato) saltiamo lo slot senza abortire.
            if (! $this->attachAndCleanup($animal, $path, 'photos', "{$animal->slug}-foto-".($variant + 1).'.jpg')) {
                continue;
            }

            // La PRIMA foto attaccata con successo diventa anche l'avatar
            // (single-file collection): riscarichiamo un file distinto da attaccare.
            if ($attached === 0) {
                $avatarPath = $this->remotePhotos->fetch($animal->species, $animal->getKey(), $variant);

                if ($avatarPath !== null) {
                    $this->attachAndCleanup($animal, $avatarPath, 'avatar', "{$animal->slug}-avatar.jpg");
                }
            }

            $attached++;
        }

        // Rete non disponibile: nessuna foto reale ottenuta → placeholder GD
        // offline (avatar + una foto) così il demo non resta muto.
        if ($attached === 0) {
            $this->attachOfflinePlaceholders($animal, $paletteSeed);
        }
    }

    /**
     * Fallback offline: 1 avatar + 1 foto placeholder GD (nessuna rete), usato
     * solo quando il download delle foto reali non è andato a buon fine.
     */
    private function attachOfflinePlaceholders(Animal $animal, int $paletteSeed): void
    {
        $avatarPath = $this->placeholderImages->create(
            label: $animal->name,
            subtitle: 'avatar',
            paletteIndex: $paletteSeed,
        );

        $this->attachAndCleanup($animal, $avatarPath, 'avatar', "{$animal->slug}-avatar.jpg");

        $photoPath = $this->placeholderImages->create(
            label: $animal->name,
            subtitle: 'foto 1',
            paletteIndex: $paletteSeed + 1,
        );

        $this->attachAndCleanup($animal, $photoPath, 'photos', "{$animal->slug}-foto-1.jpg");
    }

    /**
     * Attacca un file al media dell'animale e ripulisce la sorgente temporanea.
     *
     * Resiliente: un file remoto corrotto/troncato/scomparso (o una conversione
     * fallita) NON deve abortire l'intero seeding demo. Qualunque errore di
     * medialibrary viene loggato e assorbito; l'esito booleano dice al chiamante
     * se l'attach è andato a buon fine (per decidere avatar/fallback).
     */
    private function attachAndCleanup(Animal $animal, string $path, string $collection, string $fileName): bool
    {
        try {
            $animal->addMedia($path)
                ->usingFileName($fileName)
                ->toMediaCollection($collection);

            return true;
        } catch (\Throwable $exception) {
            Log::warning('DemoDataSeeder: attach media fallito, salto.', [
                'animal' => $animal->slug,
                'collection' => $collection,
                'reason' => $exception->getMessage(),
            ]);

            return false;
        } finally {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * Per una parte degli animali crea 1–3 cartelle sanitarie coerenti.
     * Gli allegati (referti/PDF) sono OMESSI di proposito: non essenziali al
     * dataset demo e la generazione di PDF offline aggiungerebbe complessità
     * senza valore dimostrativo (gap noto, vedi report).
     */
    private function attachMedicalRecords(Animal $animal): void
    {
        // Circa 60% degli animali ha una cartella sanitaria popolata: il restante
        // ~40% ne resta privo (early-return).
        if (! $this->faker->boolean(60)) {
            return;
        }

        // Ogni animale con cartella riceve almeno una vaccinazione con richiamo
        // futuro (utile agli scadenzari), più eventuali voci aggiuntive.
        MedicalRecord::factory()
            ->forAnimal($animal)
            ->vaccination()
            ->create();

        $extra = $this->faker->numberBetween(0, 2);

        for ($i = 0; $i < $extra; $i++) {
            // Il tipo si sceglie qui (nel seeder): la closure di state() è bound
            // alla factory, quindi $this->faker non è accessibile al suo interno.
            $type = $this->faker->randomElement([
                MedicalRecordType::Checkup,
                MedicalRecordType::Treatment,
                MedicalRecordType::Deworming,
            ]);

            MedicalRecord::factory()
                ->forAnimal($animal)
                ->state(fn (): array => ['type' => $type])
                ->create();
        }
    }

    /**
     * Riepilogo di console con credenziali e URL utili per il login demo.
     * (Stringhe di console: non fanno parte dei dati UI tradotti.)
     */
    private function printSummary(string $superAdminEmail, ?string $ownerEmail): void
    {
        $command = $this->command;

        if ($command === null) {
            return;
        }

        $command->newLine();
        $command->info('== ArkVel — dati demo creati ==');
        $command->table(
            ['Ruolo', 'Email', 'Password'],
            [
                ['Super Admin', $superAdminEmail, self::DEMO_PASSWORD],
                ['Org Owner (esempio)', $ownerEmail ?? 'n/d', self::DEMO_PASSWORD],
                ['Org Staff', 'staff+{ente}@arkvel.test', self::DEMO_PASSWORD],
                ['Org Volunteer', 'volunteer+{ente}@arkvel.test', self::DEMO_PASSWORD],
            ],
        );
        $command->line('URL pannello admin:   /admin');
        $command->line('URL pannello ente:    /manage');
        $command->newLine();
    }
}
