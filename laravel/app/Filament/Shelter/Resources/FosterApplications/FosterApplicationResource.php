<?php

declare(strict_types=1);

namespace App\Filament\Shelter\Resources\FosterApplications;

use App\Filament\Shelter\Resources\FosterApplications\Pages\ListFosterApplications;
use App\Filament\Shelter\Resources\FosterApplications\Pages\ViewFosterApplication;
use App\Filament\Shelter\Resources\FosterApplications\Schemas\FosterApplicationInfolist;
use App\Filament\Shelter\Resources\FosterApplications\Tables\FosterApplicationsTable;
use App\Models\FosterApplication;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Resource delle richieste di affido del pannello ente (shelter), tenant-scoped.
 *
 * Specchio di AdoptionApplicationResource. Multi-tenancy: la relazione di
 * ownership verso il tenant è `organization`. Impostandola qui, Filament scopa
 * automaticamente getEloquentQuery() (lista) e getRecordRouteBindingEloquentQuery()
 * (view) al tenant corrente: lo staff di un ente vede e apre SOLO le richieste di
 * affido del proprio ente. È la garanzia di isolamento cross-tenant, rafforzata
 * dalla FosterApplicationPolicy (isVerifiedMemberOf) sulle azioni.
 *
 * Nessuna Create page: le richieste nascono dal pannello adottante
 * (SubmitFosterApplication). Lo staff le REVISIONA: lista + view, con le azioni di
 * avanzamento/assegnazione/note e il ciclo di vita dell'affido fisico (avvio /
 * chiusura) nella table. Resource sottile: table e infolist vivono in classi
 * dedicate; le transizioni delegano alle Action in App\Actions\Fostering.
 */
class FosterApplicationResource extends Resource
{
    protected static ?string $model = FosterApplication::class;

    /**
     * Relazione di ownership verso il tenant (Organization): guida lo scoping
     * delle query di lista e del record binding della view.
     */
    protected static ?string $tenantOwnershipRelationshipName = 'organization';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected static ?int $navigationSort = 30;

    public static function getNavigationGroup(): ?string
    {
        return __('fostering.shelter.nav_group');
    }

    public static function getModelLabel(): string
    {
        return __('fostering.shelter.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('fostering.shelter.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('fostering.shelter.nav_label');
    }

    public static function table(Table $table): Table
    {
        return FosterApplicationsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return FosterApplicationInfolist::configure($schema);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        // Nessuna 'create': le richieste di affido originano dagli affidatari.
        return [
            'index' => ListFosterApplications::route('/'),
            'view' => ViewFosterApplication::route('/{record}'),
        ];
    }
}
