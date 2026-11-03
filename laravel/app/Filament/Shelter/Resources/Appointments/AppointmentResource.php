<?php

declare(strict_types=1);

namespace App\Filament\Shelter\Resources\Appointments;

use App\Filament\Shelter\Resources\Appointments\Pages\ListAppointments;
use App\Filament\Shelter\Resources\Appointments\Pages\ViewAppointment;
use App\Filament\Shelter\Resources\Appointments\Schemas\AppointmentInfolist;
use App\Filament\Shelter\Resources\Appointments\Tables\AppointmentsTable;
use App\Models\Appointment;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Resource degli appuntamenti del pannello ente (shelter), tenant-scoped.
 *
 * Specchio di FosterApplicationResource. Multi-tenancy: la relazione di ownership
 * verso il tenant è `organization`. Impostandola qui, Filament scopa
 * automaticamente getEloquentQuery() (lista) e getRecordRouteBindingEloquentQuery()
 * (view) al tenant corrente: lo staff di un ente vede e apre SOLO gli appuntamenti
 * del proprio ente. È la garanzia di isolamento cross-tenant, rafforzata dalla
 * AppointmentPolicy (isVerifiedMemberOf) sulle azioni.
 *
 * Nessuna Create page: gli appuntamenti nascono dal pannello adottante
 * (RequestAppointment, CTA "Prenota una visita" sulla scheda animale). Lo staff li
 * GESTISCE: lista + view, con le azioni di ciclo di vita (conferma / completa /
 * assenza / annulla) nella table, tutte delegate alle Action in
 * App\Actions\Appointments.
 *
 * SCELTA DELIBERATA — LISTA/TABELLA con filtri (data + stato + tipo), NON un
 * calendario: non esiste un plugin calendario mantenuto per Filament 5 (stessa
 * lezione appresa col plugin translatable, assente per la v5). Introdurre una
 * dipendenza non mantenuta sarebbe un rischio: la tabella con filtro per data
 * (intervallo + "solo futuri"), stato e tipo copre il caso d'uso dell'agenda
 * dell'ente senza dipendenze fragili. La logica dei filtri vive in
 * {@see AppointmentsTable}.
 */
class AppointmentResource extends Resource
{
    protected static ?string $model = Appointment::class;

    /**
     * Relazione di ownership verso il tenant (Organization): guida lo scoping delle
     * query di lista e del record binding della view.
     */
    protected static ?string $tenantOwnershipRelationshipName = 'organization';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 40;

    public static function getNavigationGroup(): ?string
    {
        return __('appointments.shelter.nav_group');
    }

    public static function getModelLabel(): string
    {
        return __('appointments.shelter.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('appointments.shelter.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('appointments.shelter.nav_label');
    }

    public static function table(Table $table): Table
    {
        return AppointmentsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AppointmentInfolist::configure($schema);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        // Nessuna 'create': gli appuntamenti originano dagli adottanti.
        return [
            'index' => ListAppointments::route('/'),
            'view' => ViewAppointment::route('/{record}'),
        ];
    }
}
