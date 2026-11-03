<?php

declare(strict_types=1);

namespace App\Filament\Shelter\Resources\Appointments\Schemas;

use App\Filament\Shelter\Resources\Animals\AnimalResource;
use App\Models\Appointment;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Infolist della view appuntamento (pannello shelter).
 *
 * Specchio di FosterApplicationInfolist. Privacy by design (GDPR): mostra del
 * richiedente solo nome ed email, quanto serve per organizzare l'incontro. Il
 * confine è garantito dalla tenancy (la Resource è organization-scoped) e dalla
 * Policy (view = AppointmentView + membership verificata): chi arriva qui è già
 * staff verificato dell'ente proprietario.
 *
 * Sottile: sola presentazione, nessuna logica. Le azioni di gestione (conferma /
 * completa / assenza / annulla) vivono nell'header della view / table.
 */
class AppointmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                self::appointmentSection(),
                self::requesterSection(),
            ]);
    }

    private static function appointmentSection(): Section
    {
        return Section::make(__('appointments.shelter.section_appointment'))
            ->columns(2)
            ->schema([
                TextEntry::make('animal.name')
                    ->label(__('appointments.shelter.animal'))
                    // Link alla scheda animale (tenant-scoped come la Resource).
                    ->url(fn (Appointment $record): string => AnimalResource::getUrl('edit', ['record' => $record->animal_id])),
                TextEntry::make('scheduled_at')
                    ->label(__('appointments.shelter.scheduled_at'))
                    ->dateTime('d/m/Y H:i'),
                TextEntry::make('type')
                    ->label(__('appointments.shelter.type'))
                    ->badge()
                    ->state(fn (Appointment $record): string => $record->type->getLabel())
                    ->color(fn (Appointment $record): string => $record->type->getColor()),
                TextEntry::make('status')
                    ->label(__('appointments.shelter.status'))
                    ->badge()
                    ->state(fn (Appointment $record): string => $record->status->getLabel())
                    ->color(fn (Appointment $record): string => $record->status->getColor()),
                TextEntry::make('notes')
                    ->label(__('appointments.shelter.notes'))
                    ->placeholder('—')
                    ->columnSpanFull(),
            ]);
    }

    private static function requesterSection(): Section
    {
        return Section::make(__('appointments.shelter.section_requester'))
            ->columns(2)
            ->schema([
                TextEntry::make('user.name')
                    ->label(__('appointments.shelter.requester')),
                TextEntry::make('user.email')
                    ->label(__('appointments.shelter.email')),
            ]);
    }
}
