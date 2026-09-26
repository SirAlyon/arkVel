<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\FosterApplicationStatus;
use App\States\Foster\FosterApplicationState;
use Database\Factories\FosterApplicationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\ModelStates\HasStates;

/**
 * Richiesta di affido: un affidatario (user) chiede l'affido temporaneo di un
 * animale di un ente (organization).
 *
 * Analoga a AdoptionApplication. Model magro: relazioni, cast e helper di
 * lettura. Nessuna business logic: il grafo delle transizioni vive in
 * App\States\Foster\FosterApplicationState e l'orchestrazione (invio, decisione,
 * ritiro) vivrà nelle Action delle slice successive. La colonna `status` è
 * castata verso lo stato ASTRATTO: il caster spatie risolve la sottoclasse
 * concreta dal valore string.
 *
 * @property int $id
 * @property int $animal_id
 * @property int $user_id
 * @property int $organization_id
 * @property int|null $assigned_to
 * @property FosterApplicationState $status
 * @property string|null $staff_notes
 * @property Carbon|null $submitted_at
 * @property Carbon|null $decided_at
 * @property string|null $decision_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Animal $animal
 * @property-read User $user
 * @property-read Organization $organization
 * @property-read User|null $assignedTo
 */
class FosterApplication extends Model
{
    /** @use HasFactory<FosterApplicationFactory> */
    use HasFactory;

    use HasStates;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'animal_id',
        'user_id',
        'organization_id',
        'assigned_to',
        'status',
        'staff_notes',
        'submitted_at',
        'decided_at',
        'decision_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // Stato ASTRATTO: il caster risolve la sottoclasse concreta.
            'status' => FosterApplicationState::class,
            'submitted_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    /**
     * Animale oggetto della richiesta.
     *
     * @return BelongsTo<Animal, $this>
     */
    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }

    /**
     * Affidatario candidato.
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
     * Operatore dell'ente che ha in carico la pratica.
     *
     * @return BelongsTo<User, $this>
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Enum di dominio dello stato corrente: comodo per riusare label/color
     * dell'enum nei badge Filament senza attraversare l'oggetto stato.
     */
    public function statusEnum(): FosterApplicationStatus
    {
        return $this->status->status();
    }
}
