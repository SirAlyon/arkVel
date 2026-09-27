<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Appuntamento fra un richiedente (adottante/affidatario) e un ente su un animale:
 * visita, colloquio o controllo domiciliare.
 *
 * Model magro: relazioni, cast e scope. Nessuna business logic: il grafo delle
 * transizioni vive in App\Enums\AppointmentStatus e l'orchestrazione (richiesta,
 * conferma, annullamento, completamento, assenza) vive nelle Action in
 * App\Actions\Appointments. La durata dello slot è una costante di dominio usata
 * dalle Action per il calcolo dell'overlap.
 *
 * @property int $id
 * @property int $animal_id
 * @property int $user_id
 * @property int $organization_id
 * @property Carbon $scheduled_at
 * @property AppointmentType $type
 * @property AppointmentStatus $status
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Animal $animal
 * @property-read User $user
 * @property-read Organization $organization
 */
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    /**
     * Durata implicita di uno slot in minuti. Fonte di verità per il calcolo
     * dell'overlap nelle Action: la finestra occupata da un appuntamento è
     * [scheduled_at, scheduled_at + SLOT_MINUTES).
     */
    public const int SLOT_MINUTES = 60;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'animal_id',
        'user_id',
        'organization_id',
        'scheduled_at',
        'type',
        'status',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'type' => AppointmentType::class,
            'status' => AppointmentStatus::class,
        ];
    }

    /**
     * Animale oggetto dell'appuntamento.
     *
     * @return BelongsTo<Animal, $this>
     */
    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }

    /**
     * Richiedente (adottante/affidatario).
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
     * Appuntamenti "vivi" (occupano uno slot): Richiesto o Confermato. Sono gli
     * unici a concorrere al calcolo dell'overlap; gli stati terminali
     * (Completed/Cancelled/NoShow) liberano lo slot.
     *
     * @param  Builder<Appointment>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->whereIn('status', [
            AppointmentStatus::Requested,
            AppointmentStatus::Confirmed,
        ]);
    }

    /**
     * Appuntamenti attivi ancora da tenersi (nel futuro): utile per l'agenda.
     *
     * @param  Builder<Appointment>  $query
     */
    #[Scope]
    protected function upcoming(Builder $query): void
    {
        $query->active()->where('scheduled_at', '>=', now());
    }
}
