<?php

declare(strict_types=1);

namespace App\Filament\Shelter\Resources\Animals\Schemas;

use App\Enums\AnimalStatus;
use App\Enums\EnergyLevel;
use App\Enums\Sex;
use App\Enums\Size;
use App\Enums\Species;
use App\Models\Animal;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;

/**
 * Schema del form Animal, condiviso da Create ed Edit (nessuna duplicazione).
 *
 * Sottile per definizione: solo definizione dei campi, validazione e visibilita
 * condizionale. Nessuna business logic (le transizioni di stato passano
 * dall'azione dedicata, vedi AnimalsTable). Lo stato NON e' liberamente
 * editabile in edit: in create si sceglie tra Draft/Available, in edit e'
 * omesso dal form (cambia solo tramite l'azione dedicata + Action con guardia).
 *
 * Il campo `description` e' multilingua (HasTranslations sul model): rendiamo un
 * editor per locale, senza dipendere dal plugin Filament translatable, che non
 * ha ancora un rilascio per Filament 5. Il fill/dehydrate del path
 * `description.<locale>` combacia con la rappresentazione ad array del cast
 * translatable, quindi la persistenza JSON multilingua e' nativa.
 */
class AnimalForm
{
    /**
     * Locali gestiti per i contenuti multilingua (base IT, fallback EN).
     *
     * @var array<string, string>
     */
    public const LOCALES = [
        'it' => 'Italiano',
        'en' => 'English',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                self::identitySection(),
                self::healthSection(),
                self::behaviourSection(),
                self::descriptionSection(),
                self::photosSection(),
                self::publicationSection(),
            ]);
    }

    private static function identitySection(): Section
    {
        return Section::make(__('Anagrafica'))
            ->columns(2)
            ->schema([
                TextInput::make('name')
                    ->label(__('Nome'))
                    ->required()
                    ->maxLength(255),
                Select::make('species')
                    ->label(__('Specie'))
                    ->options(Species::class)
                    ->enum(Species::class)
                    ->required(),
                TextInput::make('breed')
                    ->label(__('Razza'))
                    ->maxLength(255),
                Select::make('sex')
                    ->label(__('Sesso'))
                    ->options(Sex::class)
                    ->enum(Sex::class)
                    ->required(),
                Select::make('size')
                    ->label(__('Taglia'))
                    ->options(Size::class)
                    ->enum(Size::class),
                TextInput::make('microchip_number')
                    ->label(__('Microchip'))
                    ->maxLength(50)
                    // Unicita del microchip ignorando il record corrente in edit.
                    ->unique(
                        table: Animal::class,
                        column: 'microchip_number',
                        ignoreRecord: true,
                    ),
                // birthdate ed estimated_age_months sono indicativi e mutuamente
                // esclusivi nell'uso: nessuno dei due e' obbligatorio.
                DatePicker::make('birthdate')
                    ->label(__('Data di nascita'))
                    ->native(false)
                    ->maxDate(now()),
                TextInput::make('estimated_age_months')
                    ->label(__('Eta stimata (mesi)'))
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(600),
            ]);
    }

    private static function healthSection(): Section
    {
        return Section::make(__('Salute'))
            ->columns(3)
            ->schema([
                Toggle::make('sterilized')
                    ->label(__('Sterilizzato')),
                Toggle::make('vaccinated')
                    ->label(__('Vaccinato')),
                Toggle::make('dewormed')
                    ->label(__('Sverminato')),
                Textarea::make('health_notes')
                    ->label(__('Note sanitarie'))
                    ->columnSpanFull()
                    ->rows(3)
                    ->maxLength(2000),
            ]);
    }

    private static function behaviourSection(): Section
    {
        return Section::make(__('Comportamento'))
            ->columns(2)
            ->schema([
                TextInput::make('temperament')
                    ->label(__('Temperamento'))
                    ->maxLength(255),
                Select::make('energy_level')
                    ->label(__('Livello di energia'))
                    ->options(EnergyLevel::class)
                    ->enum(EnergyLevel::class)
                    ->placeholder(__('Sconosciuto')),
                // Tri-state: Si / No / Sconosciuto (null). Select::boolean rende
                // due opzioni booleane con placeholder per lo stato "sconosciuto";
                // il campo resta nullable, cosi null = sconosciuto.
                Select::make('good_with_kids')
                    ->label(__('Compatibile con bambini'))
                    ->boolean(
                        trueLabel: __('Si'),
                        falseLabel: __('No'),
                        placeholder: __('Sconosciuto'),
                    ),
                Select::make('good_with_dogs')
                    ->label(__('Compatibile con cani'))
                    ->boolean(
                        trueLabel: __('Si'),
                        falseLabel: __('No'),
                        placeholder: __('Sconosciuto'),
                    ),
                Select::make('good_with_cats')
                    ->label(__('Compatibile con gatti'))
                    ->boolean(
                        trueLabel: __('Si'),
                        falseLabel: __('No'),
                        placeholder: __('Sconosciuto'),
                    ),
            ]);
    }

    private static function descriptionSection(): Section
    {
        return Section::make(__('Descrizione'))
            ->description(__('Contenuto multilingua: compila la scheda in ogni lingua.'))
            ->columns(1)
            ->schema(
                array_map(
                    static fn (string $locale, string $language): RichEditor => RichEditor::make("description.{$locale}")
                        ->label($language)
                        ->helperText(mb_strtoupper($locale))
                        ->maxLength(20000),
                    array_keys(self::LOCALES),
                    array_values(self::LOCALES),
                ),
            );
    }

    private static function photosSection(): Section
    {
        return Section::make(__('Foto'))
            ->columns(1)
            ->schema([
                SpatieMediaLibraryFileUpload::make('avatar')
                    ->label(__('Foto principale'))
                    ->collection('avatar')
                    ->image()
                    ->acceptedFileTypes(Animal::ACCEPTED_IMAGE_MIME_TYPES)
                    ->imageEditor()
                    ->downloadable()
                    ->openable()
                    ->maxSize(5120),
                SpatieMediaLibraryFileUpload::make('photos')
                    ->label(__('Galleria foto'))
                    ->collection('photos')
                    ->image()
                    ->acceptedFileTypes(Animal::ACCEPTED_IMAGE_MIME_TYPES)
                    ->multiple()
                    ->reorderable()
                    ->appendFiles()
                    ->downloadable()
                    ->openable()
                    ->maxFiles(12)
                    ->maxSize(5120),
            ]);
    }

    private static function publicationSection(): Section
    {
        return Section::make(__('Pubblicazione'))
            ->columns(2)
            ->schema([
                Toggle::make('featured')
                    ->label(__('In evidenza')),
                Toggle::make('application_paused')
                    ->label(__('Candidature in pausa'))
                    ->live(),
                Textarea::make('pause_reason')
                    ->label(__('Motivo della pausa'))
                    ->columnSpanFull()
                    ->rows(2)
                    ->maxLength(500)
                    // pause_reason ha senso (ed e' obbligatorio) solo a pausa attiva.
                    ->visible(fn (Get $get): bool => (bool) $get('application_paused'))
                    ->required(fn (Get $get): bool => (bool) $get('application_paused')),
                // In creazione si sceglie lo stato iniziale (solo Draft o
                // Available). In edit lo stato NON e' editabile dal form: si
                // mostra read-only e cambia solo tramite l'azione dedicata
                // (guardia + activity log nell'Action).
                Select::make('status')
                    ->label(__('Stato'))
                    ->options([
                        AnimalStatus::Draft->value => AnimalStatus::Draft->getLabel(),
                        AnimalStatus::Available->value => AnimalStatus::Available->getLabel(),
                    ])
                    ->rule(Rule::in([AnimalStatus::Draft->value, AnimalStatus::Available->value]))
                    ->default(AnimalStatus::Draft->value)
                    ->required()
                    ->visibleOn('create'),
                Placeholder::make('status_display')
                    ->label(__('Stato'))
                    ->content(fn (?Animal $record): Htmlable => self::statusBadge($record?->status))
                    ->helperText(__('Usa "Cambia stato" nella lista per aggiornare lo stato.'))
                    ->visibleOn('edit'),
            ]);
    }

    /**
     * Badge read-only dello stato corrente per la pagina di edit.
     */
    private static function statusBadge(?AnimalStatus $status): Htmlable
    {
        if (! $status instanceof AnimalStatus) {
            return new HtmlString('&mdash;');
        }

        return new HtmlString(e($status->getLabel()));
    }
}
