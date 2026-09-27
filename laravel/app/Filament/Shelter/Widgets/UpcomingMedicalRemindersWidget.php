<?php

declare(strict_types=1);

namespace App\Filament\Shelter\Widgets;

use App\Models\MedicalRecord;
use App\Models\Organization;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Widget dashboard shelter — "Scadenze sanitarie in arrivo".
 *
 * Mostra le voci della cartella sanitaria degli animali del tenant corrente con una
 * scadenza (next_due_at) entro ~30 giorni o già scaduta, ordinate per data. È una
 * finestra dello scadenzario sanitario direttamente sulla dashboard dell'ente.
 *
 * TENANT SCOPING (difesa esplicita): la cartella è animal-scoped (nessuna colonna
 * organization_id), quindi filtriamo con whereHas('animal') su
 * animal.organization_id = id del tenant corrente ({@see Filament::getTenant()}).
 * Se non c'è tenant (contesto imprevisto) la query è forzata a vuoto (whereRaw '0=1')
 * per non esporre MAI righe cross-tenant. Il widget è sottile: query + colonne, la
 * logica del predicato di scadenza vive nello scope MedicalRecord::dueWithin.
 */
class UpcomingMedicalRemindersWidget extends TableWidget
{
    /**
     * Finestra dello scadenzario in dashboard: scadenze entro 30 giorni (o già
     * scadute). Più ampia della finestra di promemoria del comando (7g): la
     * dashboard dà visibilità in anticipo, il comando notifica sotto scadenza.
     */
    private const int WINDOW_DAYS = 30;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->baseQuery())
            ->heading(__('medical.widget.heading'))
            ->description(__('medical.widget.description'))
            ->defaultSort('next_due_at', 'asc')
            ->paginated([5, 10, 25])
            ->columns([
                TextColumn::make('animal.name')
                    ->label(__('medical.widget.animal'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('medical.widget.type'))
                    ->badge()
                    ->formatStateUsing(fn (MedicalRecord $record): string => $record->type->getLabel())
                    ->color(fn (MedicalRecord $record): string => $record->type->getColor()),
                TextColumn::make('next_due_at')
                    ->label(__('medical.widget.next_due_at'))
                    ->date('d/m/Y')
                    ->sortable()
                    // Rosso se la scadenza è passata (urgente), grigio altrimenti.
                    ->color(fn (MedicalRecord $record): string => $record->next_due_at !== null
                        && $record->next_due_at->isPast()
                            ? 'danger'
                            : 'gray'),
                TextColumn::make('vet_name')
                    ->label(__('medical.widget.vet_name'))
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->emptyStateHeading(__('medical.widget.empty_title'))
            ->emptyStateDescription(__('medical.widget.empty_body'));
    }

    /**
     * Query base, sempre tenant-scoped. Il filtro passa da animal.organization_id
     * perché la cartella non ha una propria colonna d'ente.
     *
     * @return Builder<MedicalRecord>
     */
    private function baseQuery(): Builder
    {
        $tenant = Filament::getTenant();

        $query = MedicalRecord::query()->dueWithin(self::WINDOW_DAYS);

        if (! $tenant instanceof Organization) {
            // Nessun tenant: non esporre nulla (fail-closed).
            return $query->whereRaw('1 = 0');
        }

        return $query->whereHas(
            'animal',
            fn (Builder $animal): Builder => $animal->where('organization_id', $tenant->getKey()),
        );
    }
}
