<?php

declare(strict_types=1);

namespace App\Filament\Shelter\Widgets;

use App\Models\Appointment;
use App\Models\Organization;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Widget dashboard shelter — "Prossimi appuntamenti".
 *
 * Mostra gli appuntamenti attivi (Richiesto|Confermato) e futuri del tenant corrente,
 * ordinati per data. È l'agenda sintetica dell'ente in dashboard.
 *
 * TENANT SCOPING (difesa esplicita): l'appuntamento porta organization_id, quindi il
 * filtro è diretto: where('organization_id', id del tenant corrente,
 * {@see Filament::getTenant()}). Senza tenant la query è forzata a vuoto per non
 * esporre MAI righe cross-tenant. Widget sottile: query + colonne, il predicato
 * "attivi & futuri" riusa lo scope Appointment::upcoming.
 */
class UpcomingAppointmentsWidget extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->baseQuery())
            ->heading(__('appointments.widget.heading'))
            ->description(__('appointments.widget.description'))
            ->defaultSort('scheduled_at', 'asc')
            ->paginated([5, 10, 25])
            ->columns([
                TextColumn::make('scheduled_at')
                    ->label(__('appointments.widget.scheduled_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('animal.name')
                    ->label(__('appointments.widget.animal'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label(__('appointments.widget.requester'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('appointments.widget.type'))
                    ->badge()
                    ->formatStateUsing(fn (Appointment $record): string => $record->type->getLabel())
                    ->color(fn (Appointment $record): string => $record->type->getColor()),
                TextColumn::make('status')
                    ->label(__('appointments.widget.status'))
                    ->badge()
                    ->formatStateUsing(fn (Appointment $record): string => $record->status->getLabel())
                    ->color(fn (Appointment $record): string => $record->status->getColor()),
            ])
            ->emptyStateHeading(__('appointments.widget.empty_title'))
            ->emptyStateDescription(__('appointments.widget.empty_body'));
    }

    /**
     * Query base, sempre tenant-scoped sul organization_id dell'appuntamento.
     *
     * @return Builder<Appointment>
     */
    private function baseQuery(): Builder
    {
        $tenant = Filament::getTenant();

        $query = Appointment::query()->upcoming();

        if (! $tenant instanceof Organization) {
            // Nessun tenant: non esporre nulla (fail-closed).
            return $query->whereRaw('1 = 0');
        }

        return $query->where('organization_id', $tenant->getKey());
    }
}
