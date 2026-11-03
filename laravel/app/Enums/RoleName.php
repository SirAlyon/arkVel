<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Str;

/**
 * Ruoli globali della piattaforma (spatie/laravel-permission).
 *
 * I ruoli sono definiti a livello globale (team_id null); le ASSEGNAZIONI
 * agli utenti portano il team id del tenant (Organization) in modalità teams.
 * I value corrispondono esattamente al campo `name` della tabella `roles`.
 */
enum RoleName: string implements HasLabel
{
    /**
     * Team id sentinel per le assegnazioni dei ruoli di PIATTAFORMA (non
     * org-scoped: SuperAdmin, Adopter). In modalità teams la colonna
     * model_has_roles.team_id è NOT NULL (parte della PK): non esiste
     * un'assegnazione "senza team". Il team è però IRRILEVANTE per questi ruoli,
     * perché il rilevamento è team-independent (query diretta sul pivot, cfr.
     * User::isSuperAdmin() ed EnsureAdopterRole). Usiamo un valore neutro (0) che
     * non coincide con alcun ente reale (le PK organizations partono da 1): unica
     * fonte di verità condivisa da seeder e middleware.
     */
    public const int PLATFORM_TEAM_ID = 0;

    case SuperAdmin = 'Super Admin';
    case OrgOwner = 'Org Owner';
    case OrgStaff = 'Org Staff';
    case OrgVolunteer = 'Org Volunteer';
    case Adopter = 'Adopter';

    public function getLabel(): string
    {
        // Il value (nome del ruolo spatie) contiene spazi: la chiave di traduzione
        // usa il nome del case in snake_case.
        return __('enums.role_name.'.Str::snake($this->name));
    }

    /**
     * Indica se il ruolo è scoped su un ente (tenant), quindi le sue
     * assegnazioni devono portare un team id.
     */
    public function isOrganizationScoped(): bool
    {
        return match ($this) {
            self::OrgOwner, self::OrgStaff, self::OrgVolunteer => true,
            self::SuperAdmin, self::Adopter => false,
        };
    }
}
