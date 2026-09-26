<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Ruoli globali della piattaforma (spatie/laravel-permission).
 *
 * I ruoli sono definiti a livello globale (team_id null); le ASSEGNAZIONI
 * agli utenti portano il team id del tenant (Organization) in modalità teams.
 * I value corrispondono esattamente al campo `name` della tabella `roles`.
 */
enum RoleName: string implements HasLabel
{
    case SuperAdmin = 'Super Admin';
    case OrgOwner = 'Org Owner';
    case OrgStaff = 'Org Staff';
    case OrgVolunteer = 'Org Volunteer';
    case Adopter = 'Adopter';

    public function getLabel(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Amministratore',
            self::OrgOwner => 'Titolare ente',
            self::OrgStaff => 'Staff ente',
            self::OrgVolunteer => 'Volontario ente',
            self::Adopter => 'Adottante',
        };
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
