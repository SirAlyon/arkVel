<?php

declare(strict_types=1);

namespace App\Filament\Shelter\Resources\Donations\Tables;

use App\Enums\DonationStatus;
use App\Models\Donation;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Number;

/**
 * Tabella delle donazioni del pannello shelter — registro di sola lettura.
 *
 * La tabella è sottile: colonne + un filtro per stato, le più recenti in cima. NESSUNA
 * azione di creazione/modifica (le donazioni nascono dal flusso pubblico, P4-6). Lo
 * scoping per tenant è garantito dalla Resource (tenantOwnershipRelationshipName =
 * organization): la tabella non filtra per ente, eredita già lo scope.
 *
 * Il donatore è mostrato per nome (dall'account o dai dati d'ospite) o "Anonimo" quando
 * assente. L'importo è formattato in euro a partire dai centesimi (fonte di verità).
 */
class DonationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('donations.shelter.date'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('donor_name')
                    ->label(__('donations.shelter.donor'))
                    ->formatStateUsing(fn (Donation $record): string => self::donorName($record))
                    ->searchable()
                    ->description(fn (Donation $record): ?string => $record->donor_email),
                TextColumn::make('amount')
                    ->label(__('donations.shelter.amount'))
                    ->formatStateUsing(fn (Donation $record): string => Number::currency(
                        $record->amountInEuros(),
                        strtoupper($record->currency),
                    ))
                    ->sortable(),
                TextColumn::make('type')
                    ->label(__('donations.shelter.type'))
                    ->badge()
                    ->formatStateUsing(fn (Donation $record): string => $record->type->getLabel())
                    ->color(fn (Donation $record): string => $record->type->getColor()),
                TextColumn::make('status')
                    ->label(__('donations.shelter.status'))
                    ->badge()
                    ->formatStateUsing(fn (Donation $record): string => $record->status->getLabel())
                    ->color(fn (Donation $record): string => $record->status->getColor())
                    ->sortable(),
                TextColumn::make('message')
                    ->label(__('donations.shelter.message'))
                    ->limit(60)
                    ->toggleable()
                    ->placeholder('—'),
            ])
            // Più recenti in cima: il registro si legge dal più nuovo.
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('donations.shelter.filter_status'))
                    ->options(DonationStatus::class),
            ])
            ->emptyStateHeading(__('donations.shelter.empty_title'))
            ->emptyStateDescription(__('donations.shelter.empty_body'))
            ->emptyStateIcon('heroicon-o-heart');
    }

    /**
     * Nome visualizzato del donatore: il nome registrato (account o ospite) o "Anonimo"
     * quando assente (donazione senza identità, non dovrebbe capitare in P4-6 ma il
     * registro resta robusto).
     */
    private static function donorName(Donation $donation): string
    {
        $name = $donation->donor_name;

        return ($name !== null && $name !== '') ? $name : __('donations.shelter.anonymous');
    }
}
