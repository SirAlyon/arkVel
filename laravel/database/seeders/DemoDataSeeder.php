<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Organizations\AddOrganizationMember;
use App\Enums\AnimalStatus;
use App\Enums\AppointmentType;
use App\Enums\FosterPlacementType;
use App\Enums\MedicalRecordType;
use App\Enums\MembershipRole;
use App\Enums\OrganizationType;
use App\Enums\RoleName;
use App\Enums\Species;
use App\Models\AdopterProfile;
use App\Models\Animal;
use App\Models\Appointment;
use App\Models\FosterApplication;
use App\Models\FosterPlacement;
use App\Models\MedicalRecord;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\Support\PlaceholderImageFactory;
use Database\Seeders\Support\RemoteAnimalPhotoFetcher;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
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

        // Adottanti/affidatari demo (con profilo completo): richiedono
        // adozioni/affidi e prenotano appuntamenti. Creati una sola volta e
        // riusati fra tutti gli enti.
        $adopters = $this->createAdopters();

        $firstOwnerEmail = null;

        foreach (self::ORGANIZATIONS as $definition) {
            [$organization, $isNew] = $this->resolveOrganization($definition);

            $ownerEmail = $this->createStaff($organization, $definition['key']);
            $firstOwnerEmail ??= $ownerEmail;

            // Gli animali (e i dati operativi di Fase 3) si seminano solo per gli
            // enti appena creati: su un DB non fresco (re-run di db:seed) evitiamo
            // di gonfiare i dati con duplicati. Il target è `migrate:fresh --seed`.
            if ($isNew) {
                $this->createAnimals($organization);
                $this->createOperationalData($organization, $adopters);
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
     * Crea alcuni ADOTTANTI/AFFIDATARI demo a credenziali note, ciascuno con un
     * profilo adottante COMPLETO (consenso GDPR incluso) così da poter inviare
     * richieste di adozione/affido, e col ruolo Adopter assegnato come per il
     * Super Admin (team sentinel di piattaforma: Adopter non è org-scoped).
     * Idempotente su email/profilo.
     *
     * @return Collection<int, User>
     */
    private function createAdopters(): Collection
    {
        /** @var array<string, string> $definitions email => nome */
        $definitions = [
            'adopter+1@arkvel.test' => 'Giulia Bianchi',
            'adopter+2@arkvel.test' => 'Marco Rossi',
            'adopter+3@arkvel.test' => 'Sofia Esposito',
            'adopter+4@arkvel.test' => 'Luca Ferrari',
        ];

        /** @var Collection<int, User> $adopters */
        $adopters = collect();

        foreach ($definitions as $email => $name) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make(self::DEMO_PASSWORD),
                    'email_verified_at' => now(),
                ],
            );

            // Profilo completo idempotente: crealo solo se assente.
            if (! $user->adopterProfile()->exists()) {
                AdopterProfile::factory()->forUser($user)->complete()->create();
            }

            $this->assignAdopterRole($user);

            $adopters->push($user);
        }

        return $adopters;
    }

    /**
     * Assegna il ruolo Adopter (di piattaforma, non org-scoped) usando il team
     * sentinel neutro, esattamente come createSuperAdmin() ed EnsureAdopterRole.
     */
    private function assignAdopterRole(User $user): void
    {
        $previousTeamId = $this->permissionRegistrar->getPermissionsTeamId();
        $this->permissionRegistrar->setPermissionsTeamId(self::PLATFORM_TEAM_ID);

        try {
            if (! $user->hasRole(RoleName::Adopter->value)) {
                $user->assignRole(RoleName::Adopter->value);
            }
        } finally {
            $this->permissionRegistrar->setPermissionsTeamId($previousTeamId);
        }
    }

    /**
     * Semina i dati operativi della Fase 3 per un ente APPENA creato: richieste e
     * collocamenti di affido (con accoppiamento animale corretto), appuntamenti in
     * vari stati/tipi e voci sanitarie in scadenza — così UI e reminder sono
     * dimostrabili. Usa le factory (stati finali) senza invocare le Action, per
     * evitare notifiche accodate e restare deterministico.
     *
     * @param  Collection<int, User>  $adopters
     */
    private function createOperationalData(Organization $organization, Collection $adopters): void
    {
        /** @var list<User> $adopterList */
        $adopterList = $adopters->values()->all();

        if ($adopterList === []) {
            return;
        }

        /** @var list<Animal> $animals */
        $animals = $organization->animals()
            ->where('status', AnimalStatus::Available)
            ->get()
            ->all();

        if ($animals === []) {
            return;
        }

        $this->seedFosterData($organization, $animals, $adopterList);
        $this->seedAppointments($organization, $animals, $adopterList);
        $this->seedMedicalDueDates($animals);
    }

    /**
     * Richieste di affido in vari stati. Ogni richiesta usa un ANIMALE distinto
     * (rispetta il vincolo unique animal_id+user_id e mantiene coerente lo stato
     * dell'animale). L'affido ATTIVO porta l'animale a Fostered e crea un placement
     * attivo; quello COMPLETATO ha un placement concluso e l'animale resta Available
     * (rientro).
     *
     * @param  list<Animal>  $animals  animali disponibili dell'ente
     * @param  list<User>  $adopters
     */
    private function seedFosterData(Organization $organization, array $animals, array $adopters): void
    {
        $adopterCount = count($adopters);
        $cursor = 0;

        // Affido ATTIVO: animale dedicato → Fostered + placement attivo.
        $activeAnimal = $animals[$cursor] ?? null;
        if ($activeAnimal !== null) {
            $cursor++;

            $application = FosterApplication::factory()
                ->forOrganization($organization)
                ->forAnimal($activeAnimal)
                ->forAdopter($adopters[0])
                ->active()
                ->create(['staff_notes' => $this->faker->sentence()]);

            FosterPlacement::factory()
                ->forApplication($application)
                ->ofType($this->faker->randomElement(FosterPlacementType::cases()))
                ->active()
                ->create(['notes' => $this->faker->sentence()]);

            $activeAnimal->update(['status' => AnimalStatus::Fostered]);
        }

        // Affido COMPLETATO: animale dedicato, placement concluso, rientro (Available).
        $completedAnimal = $animals[$cursor] ?? null;
        if ($completedAnimal !== null) {
            $cursor++;

            $application = FosterApplication::factory()
                ->forOrganization($organization)
                ->forAnimal($completedAnimal)
                ->forAdopter($adopters[1 % $adopterCount])
                ->completed()
                ->create();

            FosterPlacement::factory()
                ->forApplication($application)
                ->ofType($this->faker->randomElement(FosterPlacementType::cases()))
                ->ended()
                ->create(['notes' => $this->faker->sentence()]);
        }

        // Altri stati d'istruttoria: un animale distinto ciascuno, finché ce ne sono.
        $states = ['submitted', 'submitted', 'underReview', 'waitlisted', 'rejected', 'withdrawn'];

        foreach ($states as $offset => $state) {
            $animal = $animals[$cursor] ?? null;
            if ($animal === null) {
                break; // finiti gli animali disponibili distinti.
            }
            $cursor++;

            $factory = FosterApplication::factory()
                ->forOrganization($organization)
                ->forAnimal($animal)
                ->forAdopter($adopters[$offset % $adopterCount]);

            $factory = match ($state) {
                'underReview' => $factory->underReview(),
                'waitlisted' => $factory->waitlisted(),
                'rejected' => $factory->rejected(),
                'withdrawn' => $factory->withdrawn(),
                default => $factory->submitted(),
            };

            $factory->create();
        }
    }

    /**
     * Appuntamenti demo: tutti i tipi e un mix di stati/date. Include almeno un
     * appuntamento CONFERMATO imminente (entro 24h) con reminder_sent_at null, così
     * arkvel:send-appointment-reminders ha qualcosa da inviare nel demo.
     *
     * @param  list<Animal>  $animals
     * @param  list<User>  $adopters
     */
    private function seedAppointments(Organization $organization, array $animals, array $adopters): void
    {
        $animalCount = count($animals);
        $adopterCount = count($adopters);
        $animalFor = fn (int $i): Animal => $animals[$i % $animalCount];
        $adopterFor = fn (int $i): User => $adopters[$i % $adopterCount];

        // Confermato imminente (entro 24h): alimenta il reminder appuntamenti.
        Appointment::factory()
            ->forOrganization($organization)
            ->forAnimal($animalFor(0))
            ->forUser($adopterFor(0))
            ->ofType(AppointmentType::Visit)
            ->confirmed()
            ->at(now()->addHours(6))
            ->create(['notes' => $this->faker->sentence()]);

        // Confermato più avanti nel tempo.
        Appointment::factory()
            ->forOrganization($organization)
            ->forAnimal($animalFor(1))
            ->forUser($adopterFor(1))
            ->ofType(AppointmentType::HomeCheck)
            ->confirmed()
            ->at(now()->addDays(4)->setTime(10, 0))
            ->create(['notes' => $this->faker->sentence()]);

        // Richiesto (in attesa di conferma dello staff).
        Appointment::factory()
            ->forOrganization($organization)
            ->forAnimal($animalFor(2))
            ->forUser($adopterFor(2))
            ->ofType(AppointmentType::Interview)
            ->requested()
            ->at(now()->addDays(2)->setTime(15, 30))
            ->create();

        // Completato di recente.
        Appointment::factory()
            ->forOrganization($organization)
            ->forAnimal($animalFor(3))
            ->forUser($adopterFor(3))
            ->ofType(AppointmentType::Visit)
            ->completed()
            ->at(now()->subDays(3)->setTime(11, 0))
            ->create(['notes' => $this->faker->sentence()]);

        // Annullato.
        Appointment::factory()
            ->forOrganization($organization)
            ->forAnimal($animalFor(0))
            ->forUser($adopterFor(1))
            ->ofType(AppointmentType::Visit)
            ->cancelled()
            ->at(now()->addDays(1)->setTime(9, 0))
            ->create();

        // Assente (no-show) a un appuntamento passato: completa il ciclo di vita.
        Appointment::factory()
            ->forOrganization($organization)
            ->forAnimal($animalFor(2))
            ->forUser($adopterFor(0))
            ->ofType(AppointmentType::HomeCheck)
            ->noShow()
            ->at(now()->subDays(1)->setTime(16, 0))
            ->create();
    }

    /**
     * Aggiunge voci sanitarie con scadenza NELLA finestra dei reminder
     * (reminder_sent_at null): alcune imminenti (entro pochi giorni) e alcune già
     * scadute, così lo scadenzario del pannello /manage e
     * arkvel:send-medical-reminders sono dimostrabili.
     *
     * @param  list<Animal>  $animals
     */
    private function seedMedicalDueDates(array $animals): void
    {
        // Fino a 3 animali con un richiamo imminente (entro pochi giorni).
        foreach (array_slice($animals, 0, 3) as $animal) {
            MedicalRecord::factory()
                ->forAnimal($animal)
                ->vaccination()
                ->dueSoon()
                ->create();
        }

        // Fino a 2 richiami GIÀ SCADUTI. Usiamo i PRIMI animali (garantiti presenti:
        // ce n'è almeno uno per arrivare qui) così il caso "scaduto" è sempre
        // dimostrabile anche con pochi animali disponibili — una cartella non ha
        // vincolo unique per animale, quindi convive con la voce dueSoon.
        // La data si calcola QUI (scope del seeder): dentro la closure di state()
        // $this è bound alla factory, quindi $this->faker non sarebbe accessibile
        // (cfr. attachMedicalRecords).
        foreach (array_slice($animals, 0, 2) as $animal) {
            $overdueAt = now()->subDays($this->faker->numberBetween(2, 15));

            MedicalRecord::factory()
                ->forAnimal($animal)
                ->state(fn (): array => [
                    'type' => MedicalRecordType::Deworming,
                    'next_due_at' => $overdueAt,
                ])
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
                ['Adottante (esempio)', 'adopter+1@arkvel.test', self::DEMO_PASSWORD],
            ],
        );
        $command->line('URL pannello admin:    /admin');
        $command->line('URL pannello ente:     /manage');
        $command->line('URL pannello adottante: /account');
        $command->newLine();
        $command->line('Dati demo Fase 3: affidi (richieste + placement), appuntamenti e scadenze sanitarie.');
        $command->line('Reminder: `php artisan arkvel:send-medical-reminders` / `:send-appointment-reminders`');
        $command->line('Le email dei reminder sono visibili in Mailpit: http://localhost:8025');
        $command->newLine();
    }
}
