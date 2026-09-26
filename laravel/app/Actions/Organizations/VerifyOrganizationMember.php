<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Models\Organization;
use App\Models\User;

/**
 * Verifica un membro dello staff di un ente: caso d'uso tipico è l'Org Owner
 * che abilita a operare uno staff/volontario precedentemente invitato.
 */
class VerifyOrganizationMember
{
    public function handle(Organization $organization, User $user): void
    {
        $organization->users()->updateExistingPivot($user->getKey(), [
            'verified' => true,
        ]);
    }
}
