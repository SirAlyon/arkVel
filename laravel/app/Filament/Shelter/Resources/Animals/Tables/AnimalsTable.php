<?php

declare(strict_types=1);

namespace App\Filament\Shelter\Resources\Animals\Tables;

use App\Actions\Animals\TransitionAnimalStatus;
use App\Enums\AnimalStatus;
use App\Enums\Sex;
use App\Enums\Species;
use App\Exceptions\InvalidAnimalStatusTransition;
use App\Models\Animal;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

/**
 * Tabella Animal del pannello shelter.
 *
 * Le azioni di riga onorano AnimalPolicy (update/delete/restore/forceDelete)
 * tramite l'autorizzazione automatica delle azioni Filament. Il cambio di stato
 * NON contiene logica qui: raccoglie la transizione scelta e delega a
 * App\Actions\Animals\TransitionAnimalStatus (guardia + activity log).
 */
class AnimalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('avatar')
                    ->label(__('Foto'))
                    ->collection('avatar')
                    ->conversion('thumb')
                    ->circular(),
                TextColumn::make('name')
                    ->label(__('Nome'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('species')
                    ->label(__('Specie'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('sex')
                    ->label(__('Sesso'))
                    ->badge()
                    ->toggleable(),
                TextColumn::make('status')
                    ->label(__('Stato'))
                    ->badge()
                    ->sortable(),
                IconColumn::make('featured')
                    ->label(__('In evidenza'))
                    ->boolean()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label(__('Aggiornato'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('species')
                    ->label(__('Specie'))
                    ->options(Species::class),
                SelectFilter::make('sex')
                    ->label(__('Sesso'))
                    ->options(Sex::class),
                SelectFilter::make('status')
                    ->label(__('Stato'))
                    ->options(AnimalStatus::class),
                TernaryFilter::make('featured')
                    ->label(__('In evidenza')),
                TrashedFilter::make(),
            ])
            ->recordActions([
                self::changeStatusAction(),
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
                ForceDeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading(__('Nessun animale'))
            ->emptyStateDescription(__('Crea la prima scheda animale del tuo ente.'));
    }

    /**
     * Azione "Cambia stato": offre SOLO le transizioni consentite dallo stato
     * corrente (grafo su AnimalStatus) e delega la mutazione all'Action. Nessuna
     * logica di transizione qui: raccolta input + delega + notifica.
     */
    private static function changeStatusAction(): Action
    {
        return Action::make('changeStatus')
            ->label(__('Cambia stato'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->modalHeading(__('Cambia stato animale'))
            ->modalSubmitActionLabel(__('Applica'))
            // Autorizzazione delegata alla Policy (AnimalPolicy::update): la forma
            // a stringa risolve automaticamente la Policy sul record dell'azione
            // (coerente con EditAction/DeleteAction). Richiede animal.update ed
            // essere membro verificato dell'ente; il volontario (solo animal.view)
            // non la vede.
            ->authorize('update')
            // Visibile solo se esistono transizioni: Adopted e' terminale.
            ->visible(fn (Animal $record): bool => $record->status->allowedTransitions() !== [])
            ->schema([
                Select::make('status')
                    ->label(__('Nuovo stato'))
                    ->options(fn (Animal $record): array => self::transitionOptions($record))
                    ->required()
                    ->native(false),
            ])
            ->action(function (Animal $record, array $data): void {
                $target = AnimalStatus::from((string) $data['status']);

                try {
                    app(TransitionAnimalStatus::class)->handle($record, $target);
                } catch (InvalidAnimalStatusTransition $exception) {
                    // Difesa: non dovrebbe accadere, offriamo solo transizioni
                    // valide. Se accade, notifichiamo senza propagare l'errore.
                    Notification::make()
                        ->title(__('Transizione non consentita'))
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title(__('Stato aggiornato'))
                    ->body(__('Il nuovo stato e\' :status.', ['status' => $target->getLabel()]))
                    ->success()
                    ->send();
            });
    }

    /**
     * Transizioni ammesse dallo stato corrente, mappate a label enum.
     *
     * @return array<string, string>
     */
    private static function transitionOptions(Animal $record): array
    {
        $options = [];

        foreach ($record->status->allowedTransitions() as $status) {
            $options[$status->value] = $status->getLabel();
        }

        return $options;
    }
}
