<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrganizationStatus;
use App\Enums\RoleName;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasTenants, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Enti (tenant) a cui l'utente appartiene, con dati di membership.
     *
     * @return BelongsToMany<Organization, $this, OrganizationUser>
     */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)
            ->using(OrganizationUser::class)
            ->withPivot(['role', 'verified', 'invited_at'])
            ->withTimestamps();
    }

    /**
     * Enti di cui l'utente è membro verificato e che possono operare.
     * Sono i tenant selezionabili nel pannello shelter.
     *
     * INVARIANTE (decisione di prodotto): un ente `Pending` È operabile dai
     * suoi membri verificati (self-service pre-verifica: chi registra un ente
     * lo gestisce subito internamente). Solo `Suspended` blocca l'accesso.
     * La verifica Super Admin (piano §6) gaterà la visibilità PUBBLICA del
     * catalogo in Fase 2, NON la gestione interna dell'ente. Perciò qui il
     * filtro esclude soltanto `Suspended`, non `Pending`.
     *
     * @return BelongsToMany<Organization, $this, OrganizationUser>
     */
    public function operableOrganizations(): BelongsToMany
    {
        return $this->organizations()
            ->wherePivot('verified', true)
            ->where('organizations.status', '!=', OrganizationStatus::Suspended);
    }

    /**
     * Profilo/questionario dell'adottante (1:1). L'ownership è per utente, non
     * per ente: non è una risorsa tenant-scoped.
     *
     * @return HasOne<AdopterProfile, $this>
     */
    public function adopterProfile(): HasOne
    {
        return $this->hasOne(AdopterProfile::class);
    }

    /**
     * L'utente ha un profilo adottante completo (questionario obbligatorio +
     * consenso GDPR). Delega il giudizio di completezza al profilo stesso.
     */
    public function hasCompleteAdopterProfile(): bool
    {
        return $this->adopterProfile?->isComplete() === true;
    }

    /**
     * L'utente è membro (a qualunque titolo) dell'ente indicato.
     */
    public function belongsToOrganization(Organization $organization): bool
    {
        return $this->organizations()
            ->whereKey($organization->getKey())
            ->exists();
    }

    /**
     * L'utente è un membro verificato dell'ente indicato.
     */
    public function isVerifiedMemberOf(Organization $organization): bool
    {
        return $this->organizations()
            ->whereKey($organization->getKey())
            ->wherePivot('verified', true)
            ->exists();
    }

    /**
     * L'utente ha il ruolo di piattaforma Super Admin.
     *
     * Il Super Admin è un ruolo di PIATTAFORMA, non org-scoped. In modalità
     * teams l'assegnazione in `model_has_roles` porta sempre un team id (colonna
     * NOT NULL, parte della PK), ma quel team è IRRILEVANTE: la scope spatie
     * `$this->roles()` filtra però su `wherePivot('team_id', getPermissionsTeamId())`,
     * quindi mascherebbe l'assegnazione ogni volta che il team corrente non
     * coincide con quello usato in fase di assegnazione (tipicamente `null`
     * all'accesso al pannello, oppure un ente diverso). Rileviamo perciò il
     * ruolo con una query diretta sul pivot, TEAM-INDEPENDENT: unica fonte di
     * verità, riusata anche dal bypass Gate::before in AppServiceProvider.
     */
    public function isSuperAdmin(): bool
    {
        /** @var array<string, string> $tables */
        $tables = config('permission.table_names');
        /** @var array<string, string> $columns */
        $columns = config('permission.column_names');

        $modelHasRoles = $tables['model_has_roles'];
        $rolesTable = $tables['roles'];
        $morphKey = $columns['model_morph_key'];
        $pivotRoleKey = $columns['role_pivot_key'] ?? 'role_id';

        return DB::table($modelHasRoles)
            ->join(
                $rolesTable,
                "{$modelHasRoles}.{$pivotRoleKey}",
                '=',
                "{$rolesTable}.id",
            )
            ->where("{$modelHasRoles}.{$morphKey}", $this->getKey())
            ->where("{$modelHasRoles}.model_type", $this->getMorphClass())
            ->where("{$rolesTable}.name", RoleName::SuperAdmin->value)
            ->where("{$rolesTable}.guard_name", config('auth.defaults.guard'))
            ->exists();
    }

    /**
     * Predicato di accesso al pannello (contratto Filament).
     *
     * Modello di accesso Filament-idiomatico: l'accesso allo SHELL del pannello
     * è PERMISSIVO; l'ISOLAMENTO reale è a livello di TENANT + verifica email.
     *
     * - shelter: qualunque utente autenticato può accedere allo shell del
     *   pannello (`true`, coerente col ramo default/admin). Questo è NECESSARIO
     *   al flusso di SELF-REGISTRATION del primo ente: un utente senza alcuna
     *   membership deve poter raggiungere la tenant registration page
     *   (`/manage/new`, pagina RegisterOrganization) per creare/entrare in un
     *   ente. Gattiare qui sulla membership dava un 403 su TUTTE le rotte del
     *   pannello (inclusa `/manage/new`), rendendo la self-registration via HTTP
     *   irraggiungibile. I confini di sicurezza restano tre, e sono ALTROVE:
     *     (a) ISOLAMENTO: `getTenants()`/`canAccessTenant()` ammettono solo gli
     *         enti di cui l'utente è membro VERIFICATO e non SOSPESO; lo scoping
     *         delle Resource (global scope di tenancy + Policy) impedisce ogni
     *         fuga cross-tenant. Un non-membro non risolve né vede i record di
     *         un ente altrui (404), pur potendo entrare nello shell.
     *     (b) VERIFICA EMAIL: imposta dal middleware `verified` (abilitato da
     *         emailVerification() sul pannello). Un utente con email non
     *         verificata è rediretto alla prompt di verifica prima di
     *         raggiungere qualunque rotta protetta, `/manage/new` inclusa.
     *     (c) AUTENTICAZIONE: l'authMiddleware devia gli ospiti alla login.
     * - admin: accesso invariato (autenticazione sufficiente); il pannello admin
     *   non abilita emailVerification(), quindi il contratto MustVerifyEmail non
     *   introduce regressioni sull'admin. Il gating fine dell'admin sarà definito
     *   in una slice dedicata.
     * - adopter: accesso permissivo per ogni utente autenticato (`true`). Qui non
     *   c'è tenancy: l'ISOLAMENTO del pannello adopter è per OWNERSHIP a livello di
     *   Policy (AdopterProfilePolicy: solo il proprietario vede/modifica il proprio
     *   profilo) più la VERIFICA EMAIL obbligatoria (emailVerification() sul
     *   pannello). Il ruolo Adopter è di piattaforma, assegnato team-independent dal
     *   middleware EnsureAdopterRole, ed è bookkeeping (nessun permesso).
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'shelter' => true,
            'adopter' => true,
            default => true,
        };
    }

    /**
     * Tenant selezionabili dall'utente nel pannello (contratto HasTenants):
     * gli enti di cui è membro verificato ed operabile (non sospesi).
     *
     * @return Collection<int, Organization>
     */
    public function getTenants(Panel $panel): Collection
    {
        return $this->operableOrganizations()->get();
    }

    /**
     * L'utente può operare sul tenant indicato (contratto HasTenants):
     * deve esserne membro verificato e il tenant non deve essere sospeso.
     * Default sicuro: non-membro = nessun accesso.
     *
     * INVARIANTE (decisione di prodotto): un ente `Pending` è operabile dai
     * suoi membri verificati (self-service pre-verifica). Solo `Suspended`
     * nega l'accesso. La verifica Super Admin (piano §6) gaterà la visibilità
     * PUBBLICA del catalogo in Fase 2, NON la gestione interna: per questo il
     * predicato blocca soltanto `Suspended` e ammette `Pending`.
     */
    public function canAccessTenant(Model $tenant): bool
    {
        return $tenant instanceof Organization
            && $tenant->status !== OrganizationStatus::Suspended
            && $this->isVerifiedMemberOf($tenant);
    }
}
