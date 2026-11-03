<?php

declare(strict_types=1);

namespace App\Filament\Shelter\Resources\Donations;

use App\Enums\PermissionName;
use App\Filament\Shelter\Resources\Donations\Pages\ListDonations;
use App\Filament\Shelter\Resources\Donations\Tables\DonationsTable;
use App\Models\Donation;
use App\Models\Organization;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * Resource delle donazioni del pannello ente (shelter), tenant-scoped e SOLO LETTURA.
 *
 * Multi-tenancy: la relazione di ownership verso il tenant è `organization`. Impostandola
 * qui, Filament scopa automaticamente getEloquentQuery() (lista) al tenant corrente: lo
 * staff di un ente vede SOLO le donazioni del proprio ente. È la garanzia di isolamento
 * cross-tenant, rafforzata dalla DonationPolicy (isVerifiedMemberOf) e dal gating
 * esplicito di {@see self::canAccess()} / {@see self::canViewAny()}.
 *
 * SOLO LETTURA: le donazioni nascono dal flusso pubblico (Stripe Checkout + webhook,
 * P4-6), non dal pannello. Niente Create/Edit/Delete: una sola pagina `index` con la
 * tabella (filtro per stato, più recenti in cima). Il volontario (solo DonationView) può
 * vedere la lista; la gestione (rimborsi) è una slice futura (DonationManage).
 */
class DonationResource extends Resource
{
    protected static ?string $model = Donation::class;

    /**
     * Relazione di ownership verso il tenant (Organization): guida lo scoping della
     * query di lista al tenant corrente.
     */
    protected static ?string $tenantOwnershipRelationshipName = 'organization';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected static ?int $navigationSort = 50;

    public static function getNavigationGroup(): ?string
    {
        return __('donations.shelter.nav_group');
    }

    public static function getModelLabel(): string
    {
        return __('donations.shelter.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('donations.shelter.plural_label');
    }

    public static function getNavigationLabel(): string
    {
        return __('donations.shelter.nav_label');
    }

    /**
     * Accesso alla lista: l'utente deve poter VEDERE le donazioni (DonationView) ed
     * essere un membro VERIFICATO del tenant corrente. Lo scoping dei DATI è comunque
     * garantito dalla query tenant-scoped; qui filtriamo l'accesso alla pagina (il
     * volontario, che ha DonationView, è incluso). Mirror del gating della pagina
     * Conversations.
     */
    public static function canAccess(): bool
    {
        $user = Auth::user();
        $tenant = Filament::getTenant();

        if (! $user instanceof User || ! $tenant instanceof Organization) {
            return false;
        }

        return $user->can(PermissionName::DonationView->value)
            && $user->isVerifiedMemberOf($tenant);
    }

    public static function canViewAny(): bool
    {
        return self::canAccess();
    }

    /**
     * Nessuna creazione dal pannello: le donazioni arrivano dal flusso pubblico.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return DonationsTable::configure($table);
    }

    /**
     * @return array<string, PageRegistration>
     */
    public static function getPages(): array
    {
        // Solo index: lista di sola lettura, nessuna create/edit/view.
        return [
            'index' => ListDonations::route('/'),
        ];
    }
}
