<?php

declare(strict_types=1);

namespace App\Filament\Shelter\Resources\Animals\RelationManagers;

use App\Enums\MedicalRecordType;
use App\Models\MedicalRecord;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * RelationManager della cartella sanitaria sotto Animal (pannello shelter).
 *
 * L'owner record (Animal) è già tenant-scoped dalla Resource: la cartella
 * eredita quindi l'isolamento per tenant, senza organization_id proprio. Le
 * azioni Create/Edit/Delete onorano MedicalRecordPolicy (autorizzazione
 * automatica delle azioni Filament): il volontario (solo medical_record.view)
 * vede la lista ma non le azioni di scrittura.
 *
 * Nessuna business logic qui: sola definizione di form e table. Gli allegati
 * (referti, PDF, foto) passano dalla media-library, collection `attachments`.
 */
class MedicalRecordsRelationManager extends RelationManager
{
    protected static string $relationship = 'medicalRecords';

    protected static ?string $recordTitleAttribute = 'vet_name';

    protected static function getModelLabel(): ?string
    {
        return __('Cartella sanitaria');
    }

    protected static function getPluralModelLabel(): ?string
    {
        return __('Cartelle sanitarie');
    }

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Cartella sanitaria');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('type')
                    ->label(__('Tipo'))
                    ->options(MedicalRecordType::class)
                    ->enum(MedicalRecordType::class)
                    ->required()
                    ->native(false),
                DatePicker::make('date')
                    ->label(__('Data'))
                    ->native(false)
                    ->required()
                    ->maxDate(now()),
                TextInput::make('vet_name')
                    ->label(__('Veterinario'))
                    ->maxLength(255),
                DatePicker::make('next_due_at')
                    ->label(__('Prossima scadenza'))
                    ->native(false)
                    ->helperText(__('Es. data del prossimo richiamo o trattamento.')),
                Textarea::make('description')
                    ->label(__('Descrizione'))
                    ->columnSpanFull()
                    ->rows(3)
                    ->maxLength(2000),
                SpatieMediaLibraryFileUpload::make('attachments')
                    ->label(__('Allegati'))
                    ->collection('attachments')
                    ->multiple()
                    ->reorderable()
                    ->appendFiles()
                    // Consente di aprire in anteprima e SCARICARE i file già caricati
                    // (referti/PDF), non solo di caricarne di nuovi.
                    ->downloadable()
                    ->openable()
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(10240)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label(__('Tipo'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('date')
                    ->label(__('Data'))
                    ->date()
                    ->sortable(),
                TextColumn::make('vet_name')
                    ->label(__('Veterinario'))
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('next_due_at')
                    ->label(__('Prossima scadenza'))
                    ->date()
                    ->placeholder('—')
                    ->sortable()
                    // Evidenzia le scadenze passate (danger) e quelle imminenti
                    // (warning): lettura a colpo d'occhio dello scadenzario.
                    // Il cast `date` tronca a mezzanotte, quindi confrontiamo su
                    // granularità di giorno per non marcare come "scaduta" una
                    // voce in scadenza oggi.
                    ->color(function (?MedicalRecord $record): ?string {
                        $due = $record?->next_due_at;

                        if ($due === null) {
                            return null;
                        }

                        $today = now()->startOfDay();

                        return match (true) {
                            $due->isBefore($today) => 'danger',
                            $due->lessThanOrEqualTo($today->copy()->addWeek()) => 'warning',
                            default => null,
                        };
                    }),
            ])
            ->defaultSort('date', 'desc')
            ->filters([
                SelectFilter::make('type')
                    ->label(__('Tipo'))
                    ->options(MedicalRecordType::class),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading(__('Nessuna voce in cartella'))
            ->emptyStateDescription(__('Aggiungi vaccinazioni, trattamenti, interventi o visite di questo animale.'));
    }
}
