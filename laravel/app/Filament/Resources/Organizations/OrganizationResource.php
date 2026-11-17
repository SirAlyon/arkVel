<?php

declare(strict_types=1);

namespace App\Filament\Resources\Organizations;

use App\Actions\Organizations\SetOrganizationDemoMode;
use App\Filament\Resources\Organizations\Pages\EditOrganization;
use App\Filament\Resources\Organizations\Pages\ListOrganizations;
use App\Filament\Resources\Organizations\Schemas\OrganizationForm;
use App\Filament\Resources\Organizations\Tables\OrganizationsTable;
use App\Models\Organization;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Enti della piattaforma nel pannello Admin (/admin), SOLO per il Super Admin.
 *
 * Volutamente minima: elenco degli enti e una pagina di modifica con il solo flag
 * «Ente dimostrativo» (`is_demo`), che delega a
 * {@see SetOrganizationDemoMode}. Niente creazione né
 * cancellazione. Vive solo nel pannello Admin: lo staff degli enti (/manage) non la vede.
 *
 * Il pannello Admin è raggiungibile da ogni utente autenticato (User::canAccessPanel),
 * quindi il gating è qui, esplicito, come in StripeIntegrationSettings: la policy
 * Organization (viewAny/update) non basta, perché lo staff ha `organization.update`.
 */
class OrganizationResource extends Resource
{
    protected static ?string $model = Organization::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('demo.admin.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('demo.admin.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('demo.admin.plural_label');
    }

    private static function isSuperAdmin(): bool
    {
        return Auth::user()?->isSuperAdmin() === true;
    }

    public static function canAccess(): bool
    {
        return self::isSuperAdmin();
    }

    public static function canViewAny(): bool
    {
        return self::isSuperAdmin();
    }

    public static function canEdit(Model $record): bool
    {
        return self::isSuperAdmin();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return OrganizationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrganizationsTable::configure($table);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        return [
            'index' => ListOrganizations::route('/'),
            'edit' => EditOrganization::route('/{record}/edit'),
        ];
    }
}
