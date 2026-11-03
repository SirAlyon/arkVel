<?php

declare(strict_types=1);

namespace App\Filament\Shelter\Resources\AdoptionApplications;

use App\Filament\Shelter\Resources\AdoptionApplications\Pages\ListAdoptionApplications;
use App\Filament\Shelter\Resources\AdoptionApplications\Pages\ViewAdoptionApplication;
use App\Filament\Shelter\Resources\AdoptionApplications\Schemas\AdoptionApplicationInfolist;
use App\Filament\Shelter\Resources\AdoptionApplications\Tables\AdoptionApplicationsTable;
use App\Models\AdoptionApplication;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * Resource delle candidature di adozione del pannello ente (shelter),
 * tenant-scoped.
 *
 * Multi-tenancy: la relazione di ownership verso il tenant e' `organization`.
 * Impostandola qui, Filament scopa automaticamente getEloquentQuery() (lista) e
 * getRecordRouteBindingEloquentQuery() (view) al tenant corrente: lo staff di un
 * ente vede e apre SOLO le candidature del proprio ente. E' la garanzia di
 * isolamento cross-tenant, rafforzata dalla AdoptionApplicationPolicy
 * (isVerifiedMemberOf) sulle azioni.
 *
 * Nessuna Create page: le candidature nascono dal pannello adottante
 * (SubmitApplication, slice 7/8). Lo staff le REVISIONA: lista + view, con le
 * azioni di avanzamento/assegnazione/note nella table. Resource sottile: table e
 * infolist vivono in classi dedicate; le transizioni delegano ad
 * App\Actions\Applications\AdvanceApplication.
 */
class AdoptionApplicationResource extends Resource
{
    protected static ?string $model = AdoptionApplication::class;

    /**
     * Relazione di ownership verso il tenant (Organization): guida lo scoping
     * delle query di lista e del record binding della view.
     */
    protected static ?string $tenantOwnershipRelationshipName = 'organization';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?int $navigationSort = 20;

    public static function getNavigationGroup(): ?string
    {
        return __('applications.shelter.nav_group');
    }

    public static function getModelLabel(): string
    {
        return __('applications.shelter.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('applications.shelter.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('applications.shelter.nav_label');
    }

    public static function table(Table $table): Table
    {
        return AdoptionApplicationsTable::configure($table);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AdoptionApplicationInfolist::configure($schema);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        // Nessuna 'create': le candidature originano dagli adottanti.
        return [
            'index' => ListAdoptionApplications::route('/'),
            'view' => ViewAdoptionApplication::route('/{record}'),
        ];
    }
}
