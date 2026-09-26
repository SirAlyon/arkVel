<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrganizationStatus;
use App\Enums\OrganizationType;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Translatable\HasTranslations;

/**
 * Tenant della piattaforma (canile/rifugio/associazione/società).
 *
 * Model magro: solo cast, fillable, relazioni, scope e accessor.
 * Le relazioni verso animals/applications/adoptions verranno aggiunte nelle
 * slice successive quando i relativi model esisteranno. Logo/cover saranno
 * gestiti da spatie/laravel-medialibrary (nessuna colonna dedicata qui).
 *
 * @property int $id
 * @property string $slug
 * @property OrganizationType $type
 * @property OrganizationStatus $status
 * @property-read Collection<int, Animal> $animals
 */
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    use HasTranslations;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'type',
        'description',
        'legal_name',
        'vat_number',
        'tax_code',
        'registration_number',
        'country',
        'region',
        'province',
        'city',
        'postal_code',
        'address_line',
        'latitude',
        'longitude',
        'email',
        'phone',
        'website',
        'status',
        'verified_at',
    ];

    /**
     * @var list<string>
     */
    public array $translatable = ['description'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => OrganizationType::class,
            'status' => OrganizationStatus::class,
            'verified_at' => 'datetime',
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    /**
     * Genera lo slug da name quando non fornito esplicitamente.
     */
    protected static function booted(): void
    {
        static::creating(function (Organization $organization): void {
            if (blank($organization->slug)) {
                $organization->slug = Str::slug($organization->name);
            }
        });
    }

    /**
     * @param  Builder<Organization>  $query
     */
    #[Scope]
    protected function verified(Builder $query): void
    {
        $query->where('status', OrganizationStatus::Verified);
    }

    /**
     * Animali dell'ente. Per il catalogo pubblico filtra con lo scope
     * {@see Animal::publiclyVisible()} (o `available()`), mai grezzo.
     *
     * @return HasMany<Animal, $this>
     */
    public function animals(): HasMany
    {
        return $this->hasMany(Animal::class);
    }

    /**
     * Tutti i membri dell'ente (a qualunque titolo), con dati di membership.
     *
     * @return BelongsToMany<User, $this, OrganizationUser>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(OrganizationUser::class)
            ->withPivot(['role', 'verified', 'invited_at'])
            ->withTimestamps();
    }

    /**
     * Solo i membri verificati dell'ente (possono operare).
     *
     * @return BelongsToMany<User, $this, OrganizationUser>
     */
    public function verifiedStaff(): BelongsToMany
    {
        return $this->users()->wherePivot('verified', true);
    }
}
