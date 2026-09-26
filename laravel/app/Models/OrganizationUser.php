<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MembershipRole;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * Pivot membership utente <-> ente (organization_user).
 *
 * Model magro: solo cast. La chiave primaria è auto-incrementante (id).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $user_id
 * @property MembershipRole $role
 * @property bool $verified
 * @property Carbon|null $invited_at
 */
class OrganizationUser extends Pivot
{
    public $incrementing = true;

    protected $table = 'organization_user';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => MembershipRole::class,
            'verified' => 'bool',
            'invited_at' => 'datetime',
        ];
    }
}
