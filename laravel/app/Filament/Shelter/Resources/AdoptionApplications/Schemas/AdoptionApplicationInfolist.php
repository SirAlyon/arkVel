<?php

declare(strict_types=1);

namespace App\Filament\Shelter\Resources\AdoptionApplications\Schemas;

use App\Enums\ApplicationStatus;
use App\Filament\Shelter\Resources\Animals\AnimalResource;
use App\Models\AdopterProfile;
use App\Models\AdoptionApplication;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Infolist della view candidatura (pannello shelter).
 *
 * Privacy by design (GDPR): mostra dell'adottante SOLO i dati del questionario
 * che servono alla decisione e i contatti per organizzare il colloquio; nessun
 * dato oltre il profilo. Il confine e' garantito dalla tenancy (la Resource e'
 * organization-scoped) e dalla Policy (view = ApplicationView + membership
 * verificata): chi arriva qui e' gia' staff verificato dell'ente proprietario.
 * I campi sono minimizzati a quanto serve per valutare e contattare l'adottante.
 *
 * Sottile: sola presentazione, nessuna logica. Le azioni di gestione vivono
 * nella table / header della view.
 */
class AdoptionApplicationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                self::applicationSection(),
                self::adopterProfileSection(),
                self::contactSection(),
                self::managementSection(),
            ]);
    }

    private static function applicationSection(): Section
    {
        return Section::make(__('applications.shelter.section_application'))
            ->columns(2)
            ->schema([
                TextEntry::make('animal.name')
                    ->label(__('applications.shelter.animal'))
                    // Link alla scheda animale (tenant-scoped come la Resource).
                    ->url(fn (AdoptionApplication $record): string => AnimalResource::getUrl('edit', ['record' => $record->animal_id])),
                TextEntry::make('animal.species')
                    ->label(__('Specie'))
                    ->badge(),
                TextEntry::make('status')
                    ->label(__('applications.shelter.status'))
                    ->badge()
                    ->state(fn (AdoptionApplication $record): ApplicationStatus => $record->statusEnum()),
                TextEntry::make('assignedTo.name')
                    ->label(__('applications.shelter.assigned_to'))
                    ->placeholder(__('applications.shelter.unassigned')),
                TextEntry::make('submitted_at')
                    ->label(__('applications.shelter.submitted_at'))
                    ->dateTime()
                    ->placeholder('—'),
                TextEntry::make('decided_at')
                    ->label(__('applications.shelter.decided_at'))
                    ->dateTime()
                    ->placeholder('—'),
                TextEntry::make('decision_reason')
                    ->label(__('applications.shelter.decision_reason'))
                    ->placeholder('—')
                    ->columnSpanFull(),
            ]);
    }

    private static function adopterProfileSection(): Section
    {
        return Section::make(__('applications.shelter.section_adopter'))
            // Nota GDPR esplicita: dati minimizzati a quanto serve per decidere.
            ->description(__('applications.shelter.section_adopter_help'))
            ->columns(2)
            ->schema([
                TextEntry::make('user.name')
                    ->label(__('applications.shelter.adopter')),
                TextEntry::make('user.email')
                    ->label('Email'),
                TextEntry::make('adopter_housing_type')
                    ->label(__('applications.shelter.profile.housing_type'))
                    ->state(fn (AdoptionApplication $record): ?string => self::profile($record)?->housing_type?->getLabel())
                    ->placeholder(__('applications.shelter.unknown')),
                TextEntry::make('adopter_rent_or_own')
                    ->label(__('applications.shelter.profile.rent_or_own'))
                    ->state(fn (AdoptionApplication $record): ?string => self::profile($record)?->rent_or_own?->getLabel())
                    ->placeholder(__('applications.shelter.unknown')),
                TextEntry::make('adopter_landlord_allows_pets')
                    ->label(__('applications.shelter.profile.landlord_allows_pets'))
                    ->state(fn (AdoptionApplication $record): string => self::yesNoUnknown(self::profile($record)?->landlord_allows_pets)),
                TextEntry::make('adopter_has_garden')
                    ->label(__('applications.shelter.profile.has_garden'))
                    ->state(fn (AdoptionApplication $record): string => self::yesNoUnknown(self::profile($record)?->has_garden)),
                TextEntry::make('adopter_fenced')
                    ->label(__('applications.shelter.profile.fenced'))
                    ->state(fn (AdoptionApplication $record): string => self::yesNoUnknown(self::profile($record)?->fenced)),
                TextEntry::make('adopter_adults_in_home')
                    ->label(__('applications.shelter.profile.adults_in_home'))
                    ->state(fn (AdoptionApplication $record): ?int => self::profile($record)?->adults_in_home)
                    ->placeholder(__('applications.shelter.unknown')),
                TextEntry::make('adopter_kids_in_home')
                    ->label(__('applications.shelter.profile.kids_in_home'))
                    ->state(fn (AdoptionApplication $record): ?int => self::profile($record)?->kids_in_home)
                    ->placeholder(__('applications.shelter.unknown')),
                TextEntry::make('adopter_hours_alone_weekday')
                    ->label(__('applications.shelter.profile.hours_alone_weekday'))
                    ->state(fn (AdoptionApplication $record): ?int => self::profile($record)?->hours_alone_weekday)
                    ->placeholder(__('applications.shelter.unknown')),
                TextEntry::make('adopter_hours_alone_weekend')
                    ->label(__('applications.shelter.profile.hours_alone_weekend'))
                    ->state(fn (AdoptionApplication $record): ?int => self::profile($record)?->hours_alone_weekend)
                    ->placeholder(__('applications.shelter.unknown')),
                TextEntry::make('adopter_previous_surrender')
                    ->label(__('applications.shelter.profile.previous_surrender'))
                    ->state(fn (AdoptionApplication $record): string => self::yesNoUnknown(self::profile($record)?->previous_surrender)),
                TextEntry::make('adopter_other_pets')
                    ->label(__('applications.shelter.profile.other_pets'))
                    ->state(fn (AdoptionApplication $record): ?string => self::profile($record)?->other_pets)
                    ->placeholder(__('applications.shelter.unknown'))
                    ->columnSpanFull(),
                TextEntry::make('adopter_experience')
                    ->label(__('applications.shelter.profile.experience'))
                    ->state(fn (AdoptionApplication $record): ?string => self::profile($record)?->experience)
                    ->placeholder(__('applications.shelter.unknown'))
                    ->columnSpanFull(),
                TextEntry::make('adopter_lifestyle')
                    ->label(__('applications.shelter.profile.lifestyle'))
                    ->state(fn (AdoptionApplication $record): ?string => self::profile($record)?->lifestyle)
                    ->placeholder(__('applications.shelter.unknown'))
                    ->columnSpanFull(),
                TextEntry::make('adopter_ideal_animal')
                    ->label(__('applications.shelter.profile.ideal_animal'))
                    ->state(fn (AdoptionApplication $record): ?string => self::profile($record)?->ideal_animal)
                    ->placeholder(__('applications.shelter.unknown'))
                    ->columnSpanFull(),
                TextEntry::make('adopter_contingency_plan')
                    ->label(__('applications.shelter.profile.contingency_plan'))
                    ->state(fn (AdoptionApplication $record): ?string => self::profile($record)?->contingency_plan)
                    ->placeholder(__('applications.shelter.unknown'))
                    ->columnSpanFull(),
            ]);
    }

    private static function contactSection(): Section
    {
        return Section::make(__('applications.shelter.section_contact'))
            ->columns(2)
            ->schema([
                TextEntry::make('adopter_phone')
                    ->label(__('applications.shelter.profile.phone'))
                    ->state(fn (AdoptionApplication $record): ?string => self::profile($record)?->phone)
                    ->placeholder(__('applications.shelter.unknown')),
                TextEntry::make('adopter_city')
                    ->label(__('applications.shelter.profile.city'))
                    ->state(fn (AdoptionApplication $record): ?string => self::profile($record)?->city)
                    ->placeholder(__('applications.shelter.unknown')),
                TextEntry::make('adopter_region')
                    ->label(__('applications.shelter.profile.region'))
                    ->state(fn (AdoptionApplication $record): ?string => self::profile($record)?->region)
                    ->placeholder(__('applications.shelter.unknown')),
                TextEntry::make('adopter_country')
                    ->label(__('applications.shelter.profile.country'))
                    ->state(fn (AdoptionApplication $record): ?string => self::profile($record)?->country)
                    ->placeholder(__('applications.shelter.unknown')),
                TextEntry::make('adopter_consent')
                    ->label(__('applications.shelter.profile.consent'))
                    ->state(fn (AdoptionApplication $record): string => self::yesNoUnknown(self::profile($record)?->consent_data_processing)),
                TextEntry::make('adopter_consented_at')
                    ->label(__('applications.shelter.profile.consented_at'))
                    ->state(fn (AdoptionApplication $record): ?string => self::profile($record)?->consented_at?->translatedFormat('d/m/Y H:i'))
                    ->placeholder('—'),
            ]);
    }

    private static function managementSection(): Section
    {
        return Section::make(__('applications.shelter.section_notes'))
            ->columns(1)
            ->schema([
                TextEntry::make('staff_notes')
                    ->label(__('applications.shelter.staff_notes'))
                    ->placeholder('—')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Profilo adottante legato all'utente della candidatura (o null se non
     * compilato). L'accesso e' gia' autorizzato dalla Policy della view.
     */
    private static function profile(AdoptionApplication $record): ?AdopterProfile
    {
        return $record->user->adopterProfile;
    }

    /**
     * Rende un booleano nullable come Si / No / Non indicato.
     */
    private static function yesNoUnknown(?bool $value): string
    {
        return match ($value) {
            true => __('applications.shelter.yes'),
            false => __('applications.shelter.no'),
            null => __('applications.shelter.unknown'),
        };
    }
}
