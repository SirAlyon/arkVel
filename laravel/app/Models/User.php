<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\OrganizationStatus;
use App\Enums\RoleName;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser, HasTenants
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
     * - shelter: solo membri verificati di un ente operabile, o Super Admin.
     * - admin: accesso invariato rispetto a prima (autenticazione sufficiente);
     *   il gating fine del pannello admin sarà definito in una slice dedicata.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return match ($panel->getId()) {
            'shelter' => $this->isSuperAdmin() || $this->operableOrganizations()->exists(),
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
