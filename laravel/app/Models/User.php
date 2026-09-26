<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
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
}
