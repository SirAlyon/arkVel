<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\HousingType;
use App\Enums\RentOrOwn;
use Database\Factories\AdopterProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Profilo/questionario dell'adottante, legato 1:1 all'utente.
 *
 * Model magro: relazioni, cast e predicati di sola lettura. Nessuna business
 * logic (creazione/aggiornamento passano dalle Action della slice UI). A
 * differenza degli altri Model del dominio, l'ownership è per USER, non per
 * organizzazione: non è una risorsa d'ente e non è tenant-scoped.
 *
 * @property int $id
 * @property int $user_id
 * @property HousingType|null $housing_type
 * @property bool $has_garden
 * @property bool|null $fenced
 * @property RentOrOwn|null $rent_or_own
 * @property bool|null $landlord_allows_pets
 * @property int|null $adults_in_home
 * @property int|null $kids_in_home
 * @property string|null $other_pets
 * @property string|null $experience
 * @property int|null $hours_alone_weekday
 * @property int|null $hours_alone_weekend
 * @property string|null $lifestyle
 * @property string|null $ideal_animal
 * @property string|null $contingency_plan
 * @property bool|null $previous_surrender
 * @property string|null $referral_source
 * @property string|null $phone
 * @property string|null $country
 * @property string|null $region
 * @property string|null $city
 * @property string|null $postal_code
 * @property bool $consent_data_processing
 * @property bool $consent_marketing
 * @property Carbon|null $consented_at
 * @property-read User|null $user
 */
class AdopterProfile extends Model
{
    /** @use HasFactory<AdopterProfileFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'housing_type',
        'has_garden',
        'fenced',
        'rent_or_own',
        'landlord_allows_pets',
        'adults_in_home',
        'kids_in_home',
        'other_pets',
        'experience',
        'hours_alone_weekday',
        'hours_alone_weekend',
        'lifestyle',
        'ideal_animal',
        'contingency_plan',
        'previous_surrender',
        'referral_source',
        'phone',
        'country',
        'region',
        'city',
        'postal_code',
        'consent_data_processing',
        'consent_marketing',
        'consented_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'housing_type' => HousingType::class,
            'rent_or_own' => RentOrOwn::class,
            'has_garden' => 'boolean',
            'fenced' => 'boolean',
            'landlord_allows_pets' => 'boolean',
            'previous_surrender' => 'boolean',
            'consent_data_processing' => 'boolean',
            'consent_marketing' => 'boolean',
            'adults_in_home' => 'integer',
            'kids_in_home' => 'integer',
            'hours_alone_weekday' => 'integer',
            'hours_alone_weekend' => 'integer',
            'consented_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Il profilo è "completo" quando il questionario ha i campi obbligatori e il
     * consenso al trattamento dati è stato prestato. Queste stesse regole guidano
     * la validazione del form Filament nella slice 2: tenerle qui, in un unico
     * predicato, evita che dominio e UI divergano.
     *
     * Regole:
     *  - campi sempre obbligatori (non-null): housing_type, rent_or_own,
     *    adults_in_home, hours_alone_weekday, contingency_plan;
     *  - condizionale AFFITTO: se in affitto, il proprietario deve consentire
     *    gli animali (landlord_allows_pets === true);
     *  - condizionale GIARDINO: se c'è un giardino, va dichiarato se è recintato
     *    (fenced non-null: true o false, ma una risposta ci deve essere);
     *  - GDPR: consenso al trattamento dati prestato e datato.
     */
    public function isComplete(): bool
    {
        // 1) Campi del questionario sempre obbligatori.
        if ($this->housing_type === null
            || $this->rent_or_own === null
            || $this->adults_in_home === null
            || $this->hours_alone_weekday === null
            || $this->contingency_plan === null
        ) {
            return false;
        }

        // 2) Se in affitto, il proprietario deve consentire gli animali.
        if ($this->rent_or_own === RentOrOwn::Rent && $this->landlord_allows_pets !== true) {
            return false;
        }

        // 3) Se c'è un giardino, la risposta sulla recinzione è obbligatoria.
        if ($this->has_garden === true && $this->fenced === null) {
            return false;
        }

        // 4) GDPR: consenso al trattamento dati prestato e datato.
        return $this->consent_data_processing === true && $this->consented_at !== null;
    }
}
