<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DonationStatus;
use App\Enums\DonationType;
use Database\Factories\DonationFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Donazione a un ENTE tramite Stripe.
 *
 * ARCHITETTURA (P4-5): un solo account Stripe di PIATTAFORMA (chiavi config-gated),
 * donazioni registrate PER-ENTE. I payout per-ente (Stripe Connect) sono FUORI SCOPE /
 * enhancement futuro.
 *
 * Model magro: relazioni, cast, scope e un helper di lettura dell'importo. Nessuna
 * business logic: il grafo delle transizioni vive in App\Enums\DonationStatus e
 * l'orchestrazione (Checkout, riconciliazione webhook, rimborso) vivrà nelle Action in
 * App\Actions\Donations (P4-6/P4-7). L'importo è in CENTESIMI (intero) per non trattare
 * denaro come float, coerente con le API Stripe.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $recurring_donation_id
 * @property int|null $donor_user_id
 * @property string|null $donor_name
 * @property string|null $donor_email
 * @property int $amount importo in centesimi
 * @property string $currency
 * @property DonationType $type
 * @property DonationStatus $status
 * @property string $provider
 * @property string|null $provider_ref
 * @property string|null $message
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization $organization
 * @property-read RecurringDonation|null $recurringDonation
 * @property-read User|null $donor
 */
class Donation extends Model
{
    /** @use HasFactory<DonationFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'recurring_donation_id',
        'donor_user_id',
        'donor_name',
        'donor_email',
        'amount',
        'currency',
        'type',
        'status',
        'provider',
        'provider_ref',
        'message',
        'paid_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'type' => DonationType::class,
            'status' => DonationStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    /**
     * Ente beneficiario della donazione.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Abbonamento ricorrente che ha generato questo addebito (null per le una tantum).
     *
     * @return BelongsTo<RecurringDonation, $this>
     */
    public function recurringDonation(): BelongsTo
    {
        return $this->belongsTo(RecurringDonation::class);
    }

    /**
     * Donatore autenticato (null per donazioni anonime / da ospite).
     *
     * @return BelongsTo<User, $this>
     */
    public function donor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'donor_user_id');
    }

    /**
     * Importo in EURO (unità principale) a partire dai centesimi memorizzati: comodo per
     * la visualizzazione. La fonte di verità resta `amount` in centesimi.
     */
    public function amountInEuros(): float
    {
        return $this->amount / 100;
    }

    /**
     * Donazioni andate a buon fine (incassate). È il predicato dei KPI di raccolta.
     *
     * @param  Builder<Donation>  $query
     */
    #[Scope]
    protected function completed(Builder $query): void
    {
        $query->where('status', DonationStatus::Completed);
    }
}
