<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FosterPlacementStatus;
use App\Enums\FosterPlacementType;
use Database\Factories\FosterPlacementFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Affido fisico: la collocazione temporanea di un animale di un ente
 * (organization) presso un affidatario (user), tipicamente a partire da una
 * richiesta di affido approvata (fosterApplication).
 *
 * Model magro: relazioni, cast, scope. Nessuna business logic: la nascita
 * dell'affido (creazione dalla pratica approvata, unicità dell'affido attivo,
 * chiusura) è orchestrata dalle Action della slice 3. Lo stato Active/Ended è
 * un lifecycle SEMPLICE (enum), non una macchina a stati: la macchina vive su
 * FosterApplication.
 *
 * @property int $id
 * @property int $animal_id
 * @property int $user_id
 * @property int $organization_id
 * @property int|null $foster_application_id
 * @property FosterPlacementType $type
 * @property Carbon $start_date
 * @property Carbon|null $end_date
 * @property FosterPlacementStatus $status
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Animal $animal
 * @property-read User $user
 * @property-read Organization $organization
 * @property-read FosterApplication|null $fosterApplication
 */
class FosterPlacement extends Model
{
    /** @use HasFactory<FosterPlacementFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'animal_id',
        'user_id',
        'organization_id',
        'foster_application_id',
        'type',
        'start_date',
        'end_date',
        'status',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => FosterPlacementType::class,
            'status' => FosterPlacementStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    /**
     * Animale collocato in affido.
     *
     * @return BelongsTo<Animal, $this>
     */
    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }

    /**
     * Affidatario che ospita l'animale.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Ente proprietario dell'animale.
     *
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Pratica di affido da cui l'affido fisico è nato (può essere assente per
     * affidi diretti/di emergenza).
     *
     * @return BelongsTo<FosterApplication, $this>
     */
    public function fosterApplication(): BelongsTo
    {
        return $this->belongsTo(FosterApplication::class, 'foster_application_id');
    }

    /**
     * Solo gli affidi in corso.
     *
     * @param  Builder<FosterPlacement>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('status', FosterPlacementStatus::Active);
    }
}
