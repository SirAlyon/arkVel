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
 * @property string $name
 * @property string $slug
 * @property string|null $city
 * @property OrganizationType $type
 * @property OrganizationStatus $status
 * @property string|null $calendar_feed_token
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
        'calendar_feed_token',
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
     * Connessioni a calendari esterni (Google/CalDAV) dell'ente. Filtra con lo scope
     * {@see CalendarConnection::active()} per la pipeline di push.
     *
     * @return HasMany<CalendarConnection, $this>
     */
    public function calendarConnections(): HasMany
    {
        return $this->hasMany(CalendarConnection::class);
    }

    /**
     * Donazioni ricevute dall'ente. Filtra con lo scope {@see Donation::completed()}
     * per i KPI di raccolta (solo le donazioni incassate).
     *
     * @return HasMany<Donation, $this>
     */
    public function donations(): HasMany
    {
        return $this->hasMany(Donation::class);
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

    /**
     * Garantisce l'esistenza del token del feed iCal e lo restituisce: se manca ne
     * genera uno ad alta entropia (Str::random(48)) e lo persiste; se c'è, lo
     * restituisce invariato (idempotente). È il punto d'ingresso dalla UI (prima
     * apertura del pannello feed): genera il segreto solo quando serve.
     */
    public function ensureCalendarFeedToken(): string
    {
        if (blank($this->calendar_feed_token)) {
            $this->forceFill(['calendar_feed_token' => Str::random(48)])->save();
        }

        /** @var string $token */
        $token = $this->calendar_feed_token;

        return $token;
    }

    /**
     * Rigenera il token: assegna un nuovo segreto e persiste. Il vecchio valore è
     * sovrascritto, quindi il vecchio URL di sottoscrizione smette immediatamente di
     * funzionare (revoca). È l'azione "Rigenera/Revoca" del pannello.
     */
    public function regenerateCalendarFeedToken(): string
    {
        $token = Str::random(48);

        $this->forceFill(['calendar_feed_token' => $token])->save();

        return $token;
    }

    /**
     * URL firmato-dal-segreto del feed iCal, o null se il token non è ancora stato
     * generato. Il segreto è parte dell'URL (come le "secret ICS URL" di
     * Google/Fastmail): chi possiede l'URL può leggere il feed, quindi va condiviso
     * con cautela e può essere revocato con {@see regenerateCalendarFeedToken()}.
     */
    public function calendarFeedUrl(): ?string
    {
        if (blank($this->calendar_feed_token)) {
            return null;
        }

        return route('calendar.feed', [
            'organization' => $this->slug,
            'token' => $this->calendar_feed_token,
        ]);
    }
}
