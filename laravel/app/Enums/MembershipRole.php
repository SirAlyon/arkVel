<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Ruolo di un utente all'interno di un ente, memorizzato sulla pivot
 * `organization_user`. Mappa 1:1 verso i ruoli spatie scoped sull'ente.
 */
enum MembershipRole: string implements HasLabel
{
    case Owner = 'owner';
    case Staff = 'staff';
    case Volunteer = 'volunteer';

    public function getLabel(): string
    {
        return __('enums.membership_role.'.$this->value);
    }

    /**
     * Ruolo spatie corrispondente da assegnare (team-scoped sull'ente).
     */
    public function toRoleName(): RoleName
    {
        return match ($this) {
            self::Owner => RoleName::OrgOwner,
            self::Staff => RoleName::OrgStaff,
            self::Volunteer => RoleName::OrgVolunteer,
        };
    }
}
