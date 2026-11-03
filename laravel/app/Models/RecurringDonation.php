<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RecurringDonationStatus;
use Database\Factories\RecurringDonationFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Donazione RICORRENTE a un ENTE: l'abbonamento mensile di un utente verso un ente,
 * gestito come subscription Stripe (P4-7).
 *
 * ARCHITETTURA (P4-5): un solo account Stripe di PIATTAFORMA (chiavi config-gated); le
 * ricorrenti sono registrate PER-ENTE qui, e ogni addebito riuscito genera una riga nel
 * registro {@see Donation} (type=recurring) tramite la relazione {@see charges()}.
 *
 * Model magro: relazioni, cast e uno scope. Nessuna business logic: il grafo delle
 * transizioni vive in {@see RecurringDonationStatus}; l'orchestrazione (avvio Checkout,
 * riconciliazione webhook, disdetta) vive nelle Action in App\Actions\Donations. L'importo
 * è in CENTESIMI (intero) per non trattare denaro come float, coerente con le API Stripe.
 *
 * @property int $id
 * @property int $user_id
 * @property int $organization_id
 * @property int $amount importo del singolo addebito in centesimi
 * @property string $currency
 * @property string $interval
 * @property string|null $stripe_subscription_id
 * @property RecurringDonationStatus $status
 * @property Carbon|null $started_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $canceled_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $donor
 * @property-read Organization $organization
 * @property-read Collection<int, Donation> $charges
 */
class RecurringDonation extends Model
{
    /** @use HasFactory<RecurringDonationFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'organization_id',
        'amount',
        'currency',
        'interval',
        'stripe_subscription_id',
        'status',
        'started_at',
        'ends_at',
        'canceled_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => RecurringDonationStatus::class,
            'started_at' => 'datetime',
            'ends_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    /**
     * Utente titolare dell'abbonamento (donatore). Le ricorrenti richiedono sempre un
     * account: user_id è NOT NULL.
     *
     * @return BelongsTo<User, $this>
     */
    public function donor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Ente beneficiario della donazione ricorrente.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Addebiti (righe del registro Donation) generati da questo abbonamento: uno per
     * ogni invoice mensile andata a buon fine.
     *
     * @return HasMany<Donation, $this>
     */
    public function charges(): HasMany
    {
        return $this->hasMany(Donation::class);
    }

    /**
     * Importo del singolo addebito in EURO a partire dai centesimi: comodo per la
     * visualizzazione. La fonte di verità resta `amount` in centesimi.
     */
    public function amountInEuros(): float
    {
        return $this->amount / 100;
    }

    /**
     * Ricorrenti attualmente attive (in corso di addebito).
     *
     * @param  Builder<RecurringDonation>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', RecurringDonationStatus::Active);
    }
}
