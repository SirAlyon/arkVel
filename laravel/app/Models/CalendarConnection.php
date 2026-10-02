<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CalendarConnectionStatus;
use App\Enums\CalendarProvider;
use App\Enums\CalendarSyncDirection;
use App\Jobs\SyncAppointmentToCalendarsJob;
use App\Support\Calendar\CalendarProviderRegistry;
use Database\Factories\CalendarConnectionFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Connessione di un ente a un calendario esterno (Google/CalDAV) verso cui ArkVel
 * sincronizza gli appuntamenti.
 *
 * Model magro: relazioni, cast e scope. Nessuna business logic: la risoluzione del
 * provider ({@see CalendarProviderRegistry}) e la pipeline di
 * push ({@see SyncAppointmentToCalendarsJob}) vivono fuori dal model.
 *
 * SICUREZZA: `credentials` è cifrato a riposo (cast 'encrypted:array'): token OAuth
 * o credenziali CalDAV non compaiono mai in chiaro nel DB.
 *
 * @property int $id
 * @property int $organization_id
 * @property CalendarProvider $provider
 * @property string $name
 * @property string|null $external_calendar_id
 * @property array<string, mixed>|null $credentials
 * @property string|null $sync_token
 * @property CalendarSyncDirection $direction
 * @property CalendarConnectionStatus $status
 * @property Carbon|null $last_synced_at
 * @property string|null $last_error
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 */
class CalendarConnection extends Model
{
    /** @use HasFactory<CalendarConnectionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'provider',
        'name',
        'external_calendar_id',
        'credentials',
        'sync_token',
        'direction',
        'status',
        'last_synced_at',
        'last_error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => CalendarProvider::class,
            'direction' => CalendarSyncDirection::class,
            'status' => CalendarConnectionStatus::class,
            // Cifratura a riposo dei segreti del provider (JSON cifrato).
            'credentials' => 'encrypted:array',
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * Ente proprietario della connessione (tenant).
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Mappature appuntamento ↔ evento esterno create dal push su questa connessione.
     *
     * @return HasMany<AppointmentExternalEvent, $this>
     */
    public function externalEvents(): HasMany
    {
        return $this->hasMany(AppointmentExternalEvent::class);
    }

    /**
     * Connessioni ATTIVE: le uniche che partecipano al push. Error/Disconnected sono
     * escluse (una fallita non deve continuare a essere interrogata a ogni mutazione).
     *
     * @param  Builder<CalendarConnection>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', CalendarConnectionStatus::Active);
    }

    /**
     * Connessioni SINCRONIZZABILI dal push: Active + Error. Le Error vengono RIPROVATE
     * (un errore transitorio — etag stale, 409 recuperabile, rete — non deve disabilitare
     * la connessione per sempre): al primo push riuscito tornano Active. Solo Disconnected
     * (scelta esplicita dell'utente) resta fuori.
     *
     * @param  Builder<CalendarConnection>  $query
     */
    #[Scope]
    protected function syncable(Builder $query): void
    {
        $query->whereIn('status', [CalendarConnectionStatus::Active, CalendarConnectionStatus::Error]);
    }
}
