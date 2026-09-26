<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MedicalRecordType;
use Database\Factories\MedicalRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Voce della cartella sanitaria di un animale (vaccinazioni, trattamenti,
 * interventi, visite, sverminazioni).
 *
 * Model magro: relazioni, cast e configurazione media. Nessuna business logic.
 * La cartella è animal-scoped: l'isolamento per tenant è garantito
 * dall'animale (animal->organization), verificato da MedicalRecordPolicy;
 * per questo non esiste una colonna organization_id.
 *
 * @property int $id
 * @property int $animal_id
 * @property MedicalRecordType $type
 * @property Carbon $date
 * @property string|null $vet_name
 * @property string|null $description
 * @property Carbon|null $next_due_at
 * @property-read Animal|null $animal
 */
class MedicalRecord extends Model implements HasMedia
{
    /** @use HasFactory<MedicalRecordFactory> */
    use HasFactory;

    use InteractsWithMedia;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'animal_id',
        'type',
        'date',
        'vet_name',
        'description',
        'next_due_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MedicalRecordType::class,
            'date' => 'date',
            'next_due_at' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Animal, $this>
     */
    public function animal(): BelongsTo
    {
        return $this->belongsTo(Animal::class);
    }

    /**
     * Allegati della voce sanitaria: referti, certificati, foto. Documenti
     * eterogenei (non solo immagini), quindi accettiamo PDF e immagini.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments')
            ->acceptsMimeTypes([
                'application/pdf',
                'image/jpeg',
                'image/png',
                'image/webp',
            ]);
    }
}
