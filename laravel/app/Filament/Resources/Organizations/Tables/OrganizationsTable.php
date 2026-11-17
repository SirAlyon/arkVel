<?php

declare(strict_types=1);

namespace App\Filament\Resources\Organizations\Tables;

use App\Models\Organization;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Elenco Admin degli enti: nome, stato di verifica e flag dimostrativo, con la sola
 * azione di modifica (il flag).
 */
class OrganizationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('demo.admin.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('demo.admin.status'))
                    ->badge()
                    ->formatStateUsing(fn (Organization $record): string => $record->status->getLabel()),
                IconColumn::make('is_demo')
                    ->label(__('demo.admin.toggle'))
                    ->boolean(),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
