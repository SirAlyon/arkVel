<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AnimalStatus;
use App\Enums\EnergyLevel;
use App\Enums\Sex;
use App\Enums\Size;
use App\Enums\Species;
use App\Models\Concerns\GeneratesUniqueSlug;
use Database\Factories\AnimalFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Translatable\HasTranslations;

/**
 * Animale in adozione/affido, di proprieta di un ente (tenant).
 *
 * Model magro: relazioni, cast, scope e configurazione media. Nessuna business
 * logic: le transizioni di stato vivono in
 * App\Actions\Animals\TransitionAnimalStatus; la deduplica dello slug per-ente
 * e delegata al trait GeneratesUniqueSlug.
 *
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $slug
 * @property Species $species
 * @property string|null $breed
 * @property Sex $sex
 * @property Size|null $size
 * @property Carbon|null $birthdate
 * @property int|null $estimated_age_months
 * @property string|null $microchip_number
 * @property bool $sterilized
 * @property bool $vaccinated
 * @property bool $dewormed
 * @property string|null $health_notes
 * @property string|null $temperament
 * @property EnergyLevel|null $energy_level
 * @property bool|null $good_with_kids
 * @property bool|null $good_with_dogs
 * @property bool|null $good_with_cats
 * @property AnimalStatus $status
 * @property Carbon|null $intake_date
 * @property string|null $intake_source
 * @property bool $application_paused
 * @property string|null $pause_reason
 * @property bool $featured
 * @property-read Collection<int, MedicalRecord> $medicalRecords
 */
class Animal extends Model implements HasMedia
{
    use GeneratesUniqueSlug;

    /** @use HasFactory<AnimalFactory> */
    use HasFactory;

    use HasTranslations;
    use InteractsWithMedia;
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'name',
        'slug',
        'species',
        'breed',
        'sex',
        'size',
        'birthdate',
        'estimated_age_months',
        'microchip_number',
        'sterilized',
        'vaccinated',
        'dewormed',
        'health_notes',
        'temperament',
        'energy_level',
        'good_with_kids',
        'good_with_dogs',
        'good_with_cats',
        'description',
        'status',
        'intake_date',
        'intake_source',
        'application_paused',
        'pause_reason',
        'featured',
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
            'species' => Species::class,
            'sex' => Sex::class,
            'size' => Size::class,
            'status' => AnimalStatus::class,
            'energy_level' => EnergyLevel::class,
            'birthdate' => 'date',
            'intake_date' => 'date',
            'estimated_age_months' => 'integer',
            'sterilized' => 'boolean',
            'vaccinated' => 'boolean',
            'dewormed' => 'boolean',
            'application_paused' => 'boolean',
            'featured' => 'boolean',
            'good_with_kids' => 'boolean',
            'good_with_dogs' => 'boolean',
            'good_with_cats' => 'boolean',
        ];
    }

    /**
     * Lo slug e univoco per ente: due animali "Rex" nello stesso ente diventano
     * `rex` e `rex-2`; lo stesso slug e ammesso in enti diversi.
     *
     * @return list<string>
     */
    protected function sluggableScopeColumns(): array
    {
        return ['organization_id'];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * Cartella sanitaria: vaccinazioni, trattamenti, interventi, visite.
     *
     * @return HasMany<MedicalRecord, $this>
     */
    public function medicalRecords(): HasMany
    {
        return $this->hasMany(MedicalRecord::class);
    }

    /**
     * Solo gli animali pubblicabili nel catalogo.
     *
     * @param  Builder<Animal>  $query
     */
    #[Scope]
    protected function available(Builder $query): void
    {
        $query->where('status', AnimalStatus::Available);
    }

    /**
     * Animali in evidenza.
     *
     * @param  Builder<Animal>  $query
     */
    #[Scope]
    protected function featured(Builder $query): void
    {
        $query->where('featured', true);
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('photos')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp']);

        $this->addMediaCollection('avatar')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->singleFile();
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        // Anteprima quadrata leggera per griglie/tabelle. Sincrona (nonQueued):
        // GD e disponibile nel container, la conversione e minimale.
        $this->addMediaConversion('thumb')
            ->nonQueued()
            ->performOnCollections('photos', 'avatar')
            ->fit(Fit::Contain, 400, 400);
    }
}
