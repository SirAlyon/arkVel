<?php

declare(strict_types=1);

namespace App\Filament\Shelter\Resources\Appointments\Tables;

use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Filament\Shelter\Resources\Appointments\AppointmentResource;
use App\Filament\Shelter\Resources\Appointments\Support\StaffAppointmentActions;
use App\Models\Appointment;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Tabella degli appuntamenti del pannello shelter — la vista "agenda" dell'ente.
 *
 * SCELTA DELIBERATA — lista/tabella con filtri di DATA + STATO + TIPO, non un
 * calendario. Non esiste un plugin calendario mantenuto per Filament 5 (stessa
 * lezione del plugin translatable, assente per la v5): una dipendenza non
 * mantenuta è un rischio. La tabella con ordinamento per data e filtri (intervallo
 * di date + "solo futuri", stato, tipo) copre il caso d'uso dell'agenda senza
 * dipendenze fragili.
 *
 * La tabella è sottile: colonne + filtri + azioni. NESSUNA logica di transizione
 * qui. Le azioni di gestione (conferma / completa / assenza / annulla) vivono in
 * {@see StaffAppointmentActions} e delegano alle Action in App\Actions\Appointments;
 * l'autorizzazione è sulla AppointmentPolicy (update per la gestione forward,
 * cancel per l'annullamento). Il volontario (solo AppointmentView) non vede le
 * azioni di gestione. Lo scoping per tenant è garantito dalla Resource
 * (tenantOwnershipRelationshipName = organization): la tabella non filtra per ente,
 * eredita già lo scope.
 */
class AppointmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('scheduled_at')
                    ->label(__('appointments.shelter.scheduled_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('animal.name')
                    ->label(__('appointments.shelter.animal'))
                    ->searchable()
                    ->sortable()
                    // Link diretto alla view dell'appuntamento; l'URL della Resource
                    // è già tenant-scoped.
                    ->url(fn (Appointment $record): string => AppointmentResource::getUrl('view', ['record' => $record])),
                TextColumn::make('user.name')
                    ->label(__('appointments.shelter.requester'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('appointments.shelter.type'))
                    ->badge()
                    ->formatStateUsing(fn (Appointment $record): string => $record->type->getLabel())
                    ->color(fn (Appointment $record): string => $record->type->getColor()),
                TextColumn::make('status')
                    ->label(__('appointments.shelter.status'))
                    ->badge()
                    ->formatStateUsing(fn (Appointment $record): string => $record->status->getLabel())
                    ->color(fn (Appointment $record): string => $record->status->getColor())
                    ->sortable(),
            ])
            // Ordinamento di default per data (l'agenda si legge cronologicamente).
            ->defaultSort('scheduled_at', 'asc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('appointments.shelter.filter_status'))
                    ->options(AppointmentStatus::class),
                SelectFilter::make('type')
                    ->label(__('appointments.shelter.filter_type'))
                    ->options(AppointmentType::class),
                self::dateRangeFilter(),
                self::upcomingFilter(),
            ])
            ->recordActions([
                ViewAction::make(),
                StaffAppointmentActions::confirmAction(),
                StaffAppointmentActions::completeAction(),
                StaffAppointmentActions::noShowAction(),
                StaffAppointmentActions::cancelAction(),
            ])
            ->emptyStateHeading(__('appointments.shelter.empty_title'))
            ->emptyStateDescription(__('appointments.shelter.empty_body'));
    }

    /**
     * Filtro per intervallo di DATE su scheduled_at: due DatePicker (dal / al) che
     * restringono la finestra. È il cuore della vista "agenda" (al posto del
     * calendario): mostra gli appuntamenti in un dato periodo. Il callback riceve i
     * dati del filtro come array e applica i vincoli solo per i campi valorizzati.
     */
    public static function dateRangeFilter(): Filter
    {
        return Filter::make('scheduled_at')
            ->schema([
                DatePicker::make('from')
                    ->label(__('appointments.shelter.filter_from')),
                DatePicker::make('to')
                    ->label(__('appointments.shelter.filter_to')),
            ])
            ->query(function (Builder $query, array $data): Builder {
                return $query
                    ->when(
                        $data['from'] ?? null,
                        fn (Builder $query, mixed $date): Builder => $query->whereDate('scheduled_at', '>=', $date),
                    )
                    ->when(
                        $data['to'] ?? null,
                        fn (Builder $query, mixed $date): Builder => $query->whereDate('scheduled_at', '<=', $date),
                    );
            });
    }

    /**
     * Filtro rapido "solo futuri": appuntamenti ancora da tenersi (scheduled_at nel
     * futuro). Filtro booleano senza schema (togglabile), utile per pulire l'agenda
     * dagli appuntamenti passati.
     */
    public static function upcomingFilter(): Filter
    {
        return Filter::make('upcoming')
            ->label(__('appointments.shelter.filter_upcoming'))
            ->query(fn (Builder $query): Builder => $query->where('scheduled_at', '>=', now()));
    }
}
