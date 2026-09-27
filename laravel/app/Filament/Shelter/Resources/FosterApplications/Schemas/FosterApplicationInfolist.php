<?php

declare(strict_types=1);

namespace App\Filament\Shelter\Resources\FosterApplications\Schemas;

use App\Enums\FosterApplicationStatus;
use App\Filament\Shelter\Resources\Animals\AnimalResource;
use App\Models\AdopterProfile;
use App\Models\FosterApplication;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Infolist della view richiesta di affido (pannello shelter).
 *
 * Specchio di AdoptionApplicationInfolist. Privacy by design (GDPR): mostra
 * dell'affidatario SOLO i dati del questionario di screening che servono alla
 * decisione e i contatti per organizzare l'affido; nessun dato oltre il profilo.
 * Il confine è garantito dalla tenancy (la Resource è organization-scoped) e dalla
 * Policy (view = FosterApplicationView + membership verificata): chi arriva qui è
 * già staff verificato dell'ente proprietario.
 *
 * Sottile: sola presentazione, nessuna logica. Le azioni di gestione (avanzamento,
 * avvio/chiusura affido, assegnazione, note) vivono nella table / header della view.
 */
class FosterApplicationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                self::applicationSection(),
                self::placementSection(),
                self::carerProfileSection(),
                self::contactSection(),
                self::managementSection(),
            ]);
    }

    private static function applicationSection(): Section
    {
        return Section::make(__('fostering.shelter.section_application'))
            ->columns(2)
            ->schema([
                TextEntry::make('animal.name')
                    ->label(__('fostering.shelter.animal'))
                    // Link alla scheda animale (tenant-scoped come la Resource).
                    ->url(fn (FosterApplication $record): string => AnimalResource::getUrl('edit', ['record' => $record->animal_id])),
                TextEntry::make('animal.species')
                    ->label(__('fostering.shelter.species'))
                    ->badge(),
                TextEntry::make('status')
                    ->label(__('fostering.shelter.status'))
                    ->badge()
                    ->state(fn (FosterApplication $record): FosterApplicationStatus => $record->statusEnum()),
                TextEntry::make('assignedTo.name')
                    ->label(__('fostering.shelter.assigned_to'))
                    ->placeholder(__('fostering.shelter.unassigned')),
                TextEntry::make('submitted_at')
                    ->label(__('fostering.shelter.submitted_at'))
                    ->dateTime()
                    ->placeholder('—'),
                TextEntry::make('decided_at')
                    ->label(__('fostering.shelter.decided_at'))
                    ->dateTime()
                    ->placeholder('—'),
                TextEntry::make('decision_reason')
                    ->label(__('fostering.shelter.decision_reason'))
                    ->placeholder('—')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Dati dell'affido fisico (se presente): tipo, date, stato e note. Sezione
     * visibile solo quando la richiesta ha già un placement associato.
     */
    private static function placementSection(): Section
    {
        return Section::make(__('fostering.shelter.section_placement'))
            ->columns(2)
            ->visible(fn (FosterApplication $record): bool => $record->placement !== null)
            ->schema([
                TextEntry::make('placement.type')
                    ->label(__('fostering.shelter.placement_type'))
                    ->badge()
                    ->state(fn (FosterApplication $record): ?string => $record->placement?->type->getLabel())
                    ->color(fn (FosterApplication $record): ?string => $record->placement?->type->getColor())
                    ->placeholder('—'),
                TextEntry::make('placement.status')
                    ->label(__('fostering.shelter.placement_status'))
                    ->badge()
                    ->state(fn (FosterApplication $record): ?string => $record->placement?->status->getLabel())
                    ->color(fn (FosterApplication $record): ?string => $record->placement?->status->getColor())
                    ->placeholder('—'),
                TextEntry::make('placement.start_date')
                    ->label(__('fostering.shelter.placement_start'))
                    ->date()
                    ->placeholder('—'),
                TextEntry::make('placement.end_date')
                    ->label(__('fostering.shelter.placement_end'))
                    ->date()
                    ->placeholder('—'),
                TextEntry::make('placement.notes')
                    ->label(__('fostering.shelter.placement_notes'))
                    ->placeholder('—')
                    ->columnSpanFull(),
            ]);
    }

    private static function carerProfileSection(): Section
    {
        return Section::make(__('fostering.shelter.section_carer'))
            // Nota GDPR esplicita: dati minimizzati a quanto serve per decidere.
            ->description(__('fostering.shelter.section_carer_help'))
            ->columns(2)
            ->schema([
                TextEntry::make('user.name')
                    ->label(__('fostering.shelter.carer')),
                TextEntry::make('user.email')
                    ->label(__('fostering.shelter.email')),
                TextEntry::make('carer_housing_type')
                    ->label(__('fostering.shelter.profile.housing_type'))
                    ->state(fn (FosterApplication $record): ?string => self::profile($record)?->housing_type?->getLabel())
                    ->placeholder(__('fostering.shelter.unknown')),
                TextEntry::make('carer_rent_or_own')
                    ->label(__('fostering.shelter.profile.rent_or_own'))
                    ->state(fn (FosterApplication $record): ?string => self::profile($record)?->rent_or_own?->getLabel())
                    ->placeholder(__('fostering.shelter.unknown')),
                TextEntry::make('carer_landlord_allows_pets')
                    ->label(__('fostering.shelter.profile.landlord_allows_pets'))
                    ->state(fn (FosterApplication $record): string => self::yesNoUnknown(self::profile($record)?->landlord_allows_pets)),
                TextEntry::make('carer_has_garden')
                    ->label(__('fostering.shelter.profile.has_garden'))
                    ->state(fn (FosterApplication $record): string => self::yesNoUnknown(self::profile($record)?->has_garden)),
                TextEntry::make('carer_fenced')
                    ->label(__('fostering.shelter.profile.fenced'))
                    ->state(fn (FosterApplication $record): string => self::yesNoUnknown(self::profile($record)?->fenced)),
                TextEntry::make('carer_adults_in_home')
                    ->label(__('fostering.shelter.profile.adults_in_home'))
                    ->state(fn (FosterApplication $record): ?int => self::profile($record)?->adults_in_home)
                    ->placeholder(__('fostering.shelter.unknown')),
                TextEntry::make('carer_kids_in_home')
                    ->label(__('fostering.shelter.profile.kids_in_home'))
                    ->state(fn (FosterApplication $record): ?int => self::profile($record)?->kids_in_home)
                    ->placeholder(__('fostering.shelter.unknown')),
                TextEntry::make('carer_other_pets')
                    ->label(__('fostering.shelter.profile.other_pets'))
                    ->state(fn (FosterApplication $record): ?string => self::profile($record)?->other_pets)
                    ->placeholder(__('fostering.shelter.unknown'))
                    ->columnSpanFull(),
                TextEntry::make('carer_experience')
                    ->label(__('fostering.shelter.profile.experience'))
                    ->state(fn (FosterApplication $record): ?string => self::profile($record)?->experience)
                    ->placeholder(__('fostering.shelter.unknown'))
                    ->columnSpanFull(),
                TextEntry::make('carer_contingency_plan')
                    ->label(__('fostering.shelter.profile.contingency_plan'))
                    ->state(fn (FosterApplication $record): ?string => self::profile($record)?->contingency_plan)
                    ->placeholder(__('fostering.shelter.unknown'))
                    ->columnSpanFull(),
            ]);
    }

    private static function contactSection(): Section
    {
        return Section::make(__('fostering.shelter.section_contact'))
            ->columns(2)
            ->schema([
                TextEntry::make('carer_phone')
                    ->label(__('fostering.shelter.profile.phone'))
                    ->state(fn (FosterApplication $record): ?string => self::profile($record)?->phone)
                    ->placeholder(__('fostering.shelter.unknown')),
                TextEntry::make('carer_city')
                    ->label(__('fostering.shelter.profile.city'))
                    ->state(fn (FosterApplication $record): ?string => self::profile($record)?->city)
                    ->placeholder(__('fostering.shelter.unknown')),
                TextEntry::make('carer_region')
                    ->label(__('fostering.shelter.profile.region'))
                    ->state(fn (FosterApplication $record): ?string => self::profile($record)?->region)
                    ->placeholder(__('fostering.shelter.unknown')),
                TextEntry::make('carer_country')
                    ->label(__('fostering.shelter.profile.country'))
                    ->state(fn (FosterApplication $record): ?string => self::profile($record)?->country)
                    ->placeholder(__('fostering.shelter.unknown')),
                TextEntry::make('carer_consent')
                    ->label(__('fostering.shelter.profile.consent'))
                    ->state(fn (FosterApplication $record): string => self::yesNoUnknown(self::profile($record)?->consent_data_processing)),
                TextEntry::make('carer_consented_at')
                    ->label(__('fostering.shelter.profile.consented_at'))
                    ->state(fn (FosterApplication $record): ?string => self::profile($record)?->consented_at?->translatedFormat('d/m/Y H:i'))
                    ->placeholder('—'),
            ]);
    }

    private static function managementSection(): Section
    {
        return Section::make(__('fostering.shelter.section_notes'))
            ->columns(1)
            ->schema([
                TextEntry::make('staff_notes')
                    ->label(__('fostering.shelter.staff_notes'))
                    ->placeholder('—')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Profilo affidatario legato all'utente della richiesta (o null se non
     * compilato). L'accesso è già autorizzato dalla Policy della view. Riusa
     * l'AdopterProfile: chi accoglie in casa supera lo stesso screening.
     */
    private static function profile(FosterApplication $record): ?AdopterProfile
    {
        return $record->user->adopterProfile;
    }

    /**
     * Rende un booleano nullable come Sì / No / Non indicato.
     */
    private static function yesNoUnknown(?bool $value): string
    {
        return match ($value) {
            true => __('fostering.shelter.yes'),
            false => __('fostering.shelter.no'),
            null => __('fostering.shelter.unknown'),
        };
    }
}
