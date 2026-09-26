<?php

declare(strict_types=1);

namespace App\Filament\Shelter\Resources\Animals;

use App\Filament\Shelter\Resources\Animals\Pages\CreateAnimal;
use App\Filament\Shelter\Resources\Animals\Pages\EditAnimal;
use App\Filament\Shelter\Resources\Animals\Pages\ListAnimals;
use App\Filament\Shelter\Resources\Animals\Schemas\AnimalForm;
use App\Filament\Shelter\Resources\Animals\Tables\AnimalsTable;
use App\Models\Animal;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Resource Animal del pannello ente (shelter), tenant-scoped.
 *
 * Multi-tenancy: la relazione di ownership verso il tenant e' `organization`.
 * Impostandola qui, Filament (a) scopa automaticamente getEloquentQuery() al
 * tenant corrente e (b) associa organization_id in creazione. Le azioni
 * onorano AnimalPolicy: il volontario (solo animal.view) non vede create/edit;
 * lo scoping + la Policy (isVerifiedMemberOf) escludono fughe cross-tenant.
 *
 * Resource sottile: form e table sono definiti in classi dedicate (AnimalForm,
 * AnimalsTable); il cambio stato delega a TransitionAnimalStatus.
 */
class AnimalResource extends Resource
{
    protected static ?string $model = Animal::class;

    /**
     * Relazione di ownership verso il tenant (Organization): guida lo scoping
     * delle query e l'auto-associazione di organization_id in creazione.
     */
    protected static ?string $tenantOwnershipRelationshipName = 'organization';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * Il navigation group e' rivolto all'utente: va tradotto (CLAUDE.md §5 i18n).
     * Non puo' essere una proprieta statica (nessun contesto per __()): lo
     * esponiamo via getter, coerente con gli altri label getter tradotti.
     */
    public static function getNavigationGroup(): ?string
    {
        return __('Gestione');
    }

    public static function getModelLabel(): string
    {
        return __('Animale');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Animali');
    }

    public static function getNavigationLabel(): string
    {
        return __('Animali');
    }

    public static function form(Schema $schema): Schema
    {
        return AnimalForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AnimalsTable::configure($table);
    }

    /**
     * @return array<class-string>
     */
    public static function getRelations(): array
    {
        // RelationManager MedicalRecord: slice 6.
        return [];
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListAnimals::route('/'),
            'create' => CreateAnimal::route('/create'),
            'edit' => EditAnimal::route('/{record}/edit'),
        ];
    }

    /**
     * Il record binding delle rotte include i soft-deleted, cosi le pagine e le
     * azioni di restore/forceDelete raggiungono anche gli animali cestinati.
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
