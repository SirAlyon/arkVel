<?php

declare(strict_types=1);

namespace App\Filament\Adopter\Schemas;

use App\Enums\HousingType;
use App\Enums\RentOrOwn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Schema del questionario dell'adottante (pannello /account).
 *
 * Sottile per definizione: solo campi, validazione e visibilità condizionale.
 * Nessuna business logic. Le regole condizionali qui rispecchiano ESATTAMENTE il
 * predicato di dominio AdopterProfile::isComplete(), così UI e dominio non
 * divergono:
 *  - AFFITTO: se rent_or_own === Rent, landlord_allows_pets è obbligatorio e
 *    deve essere accettato (accepted) per un profilo completo;
 *  - GIARDINO: se has_garden è true, fenced è obbligatorio (serve una risposta).
 *
 * NB: questo schema descrive SOLO il questionario. Il consenso GDPR
 * (consent_data_processing / consent_marketing) e la cattura del timestamp
 * vivono nella Page, dove si gestisce lo stato di salvataggio.
 */
class AdopterProfileForm
{
    /**
     * Le Section del questionario, come lista di componenti riusabile. La Page le
     * innesta accanto alla Section del consenso GDPR (che vive nella Page perché
     * ne gestisce lo stato di salvataggio).
     *
     * @return list<Section>
     */
    public static function sections(): array
    {
        return [
            self::housingSection(),
            self::householdSection(),
            self::experienceSection(),
            self::referralSection(),
            self::contactsSection(),
        ];
    }

    private static function housingSection(): Section
    {
        return Section::make('Abitazione')
            ->columns(2)
            ->schema([
                Select::make('housing_type')
                    ->label('Tipo di abitazione')
                    ->options(HousingType::class)
                    ->enum(HousingType::class)
                    ->required(),
                Select::make('rent_or_own')
                    ->label('Titolo di godimento')
                    ->options(RentOrOwn::class)
                    ->enum(RentOrOwn::class)
                    ->required()
                    ->live(),
                Toggle::make('has_garden')
                    ->label('Ha un giardino')
                    ->live(),
                // Recinzione: rilevante e obbligatoria solo con giardino
                // (rispecchia isComplete(): has_garden ⇒ fenced non-null). Come per
                // landlord_allows_pets, resta sempre deidratato: `hidden` gestisce
                // solo la resa UI, la regola `required` è applicata solo col giardino.
                Toggle::make('fenced')
                    ->label('Il giardino è recintato')
                    ->hidden(fn (Get $get): bool => ! (bool) $get('has_garden'))
                    ->dehydrated()
                    ->required(fn (Get $get): bool => (bool) $get('has_garden')),
                // Consenso del proprietario: rilevante e OBBLIGATORIO in affitto;
                // deve essere accettato (accepted) come da isComplete(). Il campo
                // resta SEMPRE idratato/deidratato (dehydrated true) così il suo
                // valore e la sua validazione non dipendono dalla visibilità
                // reattiva: `hiddenWhen` gestisce solo la resa UI (nascosto se non
                // in affitto), mentre la regola `accepted` è applicata SOLO in
                // affitto. Fuori dall'affitto resta nullable (nessun vincolo).
                Toggle::make('landlord_allows_pets')
                    ->label('Il proprietario consente animali')
                    ->hidden(fn (Get $get): bool => ! self::isRenting($get))
                    ->dehydrated()
                    ->accepted(fn (Get $get): bool => self::isRenting($get))
                    ->validationMessages([
                        'accepted' => 'In affitto, è necessario il consenso del proprietario per gli animali.',
                    ]),
            ]);
    }

    /**
     * Vero quando l'abitazione è in affitto. Normalizza lo stato del Select, che
     * a runtime può essere l'enum o il suo value string (a seconda del round-trip
     * di fill/dehydrate), così le regole condizionali sono robuste in ogni caso.
     */
    private static function isRenting(Get $get): bool
    {
        $value = $get('rent_or_own');

        if ($value instanceof RentOrOwn) {
            return $value === RentOrOwn::Rent;
        }

        return $value === RentOrOwn::Rent->value;
    }

    private static function householdSection(): Section
    {
        return Section::make('Nucleo familiare')
            ->columns(2)
            ->schema([
                TextInput::make('adults_in_home')
                    ->label('Adulti in casa')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(20)
                    ->required(),
                TextInput::make('kids_in_home')
                    ->label('Minori in casa')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(20),
            ]);
    }

    private static function experienceSection(): Section
    {
        return Section::make('Esperienza e stile di vita')
            ->columns(2)
            ->schema([
                Textarea::make('experience')
                    ->label('Esperienza con gli animali')
                    ->rows(3)
                    ->maxLength(2000)
                    ->columnSpanFull(),
                Textarea::make('other_pets')
                    ->label('Altri animali già presenti')
                    ->rows(2)
                    ->maxLength(1000)
                    ->columnSpanFull(),
                TextInput::make('hours_alone_weekday')
                    ->label('Ore da solo (giorni feriali)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(24)
                    ->required(),
                TextInput::make('hours_alone_weekend')
                    ->label('Ore da solo (weekend)')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(24),
                Textarea::make('lifestyle')
                    ->label('Stile di vita')
                    ->rows(3)
                    ->maxLength(2000)
                    ->columnSpanFull(),
                Textarea::make('ideal_animal')
                    ->label('Animale ideale')
                    ->rows(3)
                    ->maxLength(2000)
                    ->columnSpanFull(),
                Textarea::make('contingency_plan')
                    ->label('Piano in caso di imprevisti')
                    ->helperText('Chi si prende cura dell\'animale se non puoi più farlo tu.')
                    ->rows(3)
                    ->maxLength(2000)
                    ->required()
                    ->columnSpanFull(),
                Toggle::make('previous_surrender')
                    ->label('Hai già ceduto/restituito un animale in passato'),
            ]);
    }

    private static function referralSection(): Section
    {
        return Section::make('Provenienza')
            ->columns(1)
            ->schema([
                TextInput::make('referral_source')
                    ->label('Come ci hai conosciuto')
                    ->maxLength(255),
            ]);
    }

    private static function contactsSection(): Section
    {
        return Section::make('Contatti')
            ->columns(2)
            ->schema([
                TextInput::make('phone')
                    ->label('Telefono')
                    ->tel()
                    ->maxLength(30),
                TextInput::make('country')
                    ->label('Paese')
                    ->maxLength(2)
                    ->default('IT'),
                TextInput::make('region')
                    ->label('Regione')
                    ->maxLength(255),
                TextInput::make('city')
                    ->label('Città')
                    ->maxLength(255),
                TextInput::make('postal_code')
                    ->label('CAP')
                    ->maxLength(16),
            ]);
    }
}
