<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FollowUpStatus;
use Database\Factories\AdoptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Adozione finalizzata: l'atto definitivo con cui un adottante (user) adotta un
 * animale di un ente (organization), a partire da una candidatura (application).
 *
 * Model magro: relazioni, cast e fillable. Nessuna business logic: la nascita
 * dell'adozione (creazione, transizione animale, contratto, notifica) è
 * orchestrata da App\Actions\Adoptions\FinalizeAdoption. L'adozione NON si crea
 * né si modifica dall'UI: è un record generato dal dominio.
 *
 * @property int $id
 * @property int $animal_id
 * @property int $user_id
 * @property int $organization_id
 * @property int $application_id
 * @property Carbon $adopted_at
 * @property string|null $fee cast `decimal:2` → stringa a runtime (es. "50.00")
 * @property string $currency
 * @property string|null $contract_path
 * @property FollowUpStatus $follow_up_status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Animal $animal
 * @property-read User $user
 * @property-read Organization $organization
 * @property-read AdoptionApplication $application
 */
class Adoption extends Model
{
    /** @use HasFactory<AdoptionFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'animal_id',
        'user_id',
        'organization_id',
        'application_id',
        'adopted_at',
        'fee',
        'currency',
        'contract_path',
        'follow_up_status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'adopted_at' => 'datetime',
            'fee' => 'decimal:2',
            'follow_up_status' => FollowUpStatus::class,
        ];
    }

    /**
     * Animale adottato.
     *
     * @return BelongsTo<Animal, $this>
     */
    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }

    /**
     * Adottante.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Ente che ha finalizzato l'adozione.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Candidatura da cui l'adozione è stata finalizzata (1:1).
     *
     * @return BelongsTo<AdoptionApplication, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(AdoptionApplication::class, 'application_id');
    }
}
