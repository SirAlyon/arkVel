<?php

declare(strict_types=1);

namespace App\Filament\Shelter\Pages;

use App\Enums\CalendarConnectionStatus;
use App\Enums\CalendarProvider;
use App\Enums\CalendarSyncDirection;
use App\Models\CalendarConnection;
use App\Models\Organization;
use App\Models\User;
use App\Policies\CalendarConnectionPolicy;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * Pagina di gestione delle connessioni a calendari esterni del pannello ente.
 *
 * SCOPE (Cal-4): elenca le connessioni del TENANT corrente, permette la
 * disconnessione (cancellazione + cascade delle mappature) e la creazione di una
 * connessione DIMOSTRATIVA (provider "log") — l'unica utilizzabile senza credenziali,
 * che rende la pipeline di push dimostrabile subito. I flussi reali (Google/CalDAV)
 * arrivano in Cal-5/6: qui sono placeholder DISABILITATI, con avviso.
 *
 * Pagina SOTTILE: interroga tenant-scoped e autorizza; nessuna business logic (la
 * pipeline di push vive nel job + observer). Isolamento: la tabella filtra sempre per
 * organization_id del tenant risolto ({@see Filament::getTenant()}) e ogni azione è
 * gated dalla {@see CalendarConnectionPolicy} (CalendarManage +
 * membership verificata) — il volontario è respinto.
 */
class CalendarConnections extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected string $view = 'filament.shelter.pages.calendar-connections';

    protected static ?string $slug = 'calendar-sync';

    /**
     * Sotto il calendario e la lista appuntamenti, nel gruppo Appuntamenti.
     */
    protected static ?int $navigationSort = 45;

    public static function getNavigationGroup(): ?string
    {
        return __('calendar.connections.nav_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('calendar.connections.nav');
    }

    public function getTitle(): string
    {
        return __('calendar.connections.title');
    }

    public function getSubheading(): ?string
    {
        return __('calendar.connections.subheading');
    }

    /**
     * Accesso: l'utente deve poter gestire le connessioni (viewAny sulla policy =
     * CalendarManage) ed essere membro VERIFICATO del tenant corrente. Il volontario
     * (senza CalendarManage) non vede la pagina. Lo scoping dei DATI resta garantito
     * dalla query della tabella (organization_id = tenant).
     */
    public static function canAccess(): bool
    {
        $user = Auth::user();
        $tenant = Filament::getTenant();

        if (! $user instanceof User || ! $tenant instanceof Organization) {
            return false;
        }

        return $user->can('viewAny', CalendarConnection::class)
            && $user->isVerifiedMemberOf($tenant);
    }

    /**
     * Tabella delle connessioni del tenant corrente. ISOLAMENTO: filtra esplicitamente
     * per organization_id = tenant risolto — nessuna connessione di altri enti compare.
     */
    public function table(Table $table): Table
    {
        $tenant = Filament::getTenant();
        $organizationId = $tenant instanceof Organization ? $tenant->getKey() : 0;

        return $table
            ->query(
                CalendarConnection::query()
                    ->where('organization_id', $organizationId)
                    ->latest(),
            )
            ->columns([
                TextColumn::make('provider')
                    ->label(__('calendar.connections.provider'))
                    ->badge()
                    ->formatStateUsing(fn (CalendarConnection $record): string => $record->provider->getLabel()),
                TextColumn::make('name')
                    ->label(__('calendar.connections.name'))
                    ->weight('bold'),
                TextColumn::make('status')
                    ->label(__('calendar.connections.status'))
                    ->badge()
                    ->formatStateUsing(fn (CalendarConnection $record): string => $record->status->getLabel())
                    ->color(fn (CalendarConnection $record): string => $record->status->getColor()),
                TextColumn::make('direction')
                    ->label(__('calendar.connections.direction'))
                    ->formatStateUsing(fn (CalendarConnection $record): string => $record->direction->getLabel())
                    ->toggleable(),
                TextColumn::make('last_synced_at')
                    ->label(__('calendar.connections.last_synced_at'))
                    ->dateTime('d/m/Y H:i')
                    ->placeholder(__('calendar.connections.never_synced'))
                    ->sortable(),
                TextColumn::make('last_error')
                    ->label(__('calendar.connections.last_error'))
                    ->wrap()
                    ->limit(60)
                    ->toggleable()
                    ->placeholder('—'),
            ])
            ->recordActions([
                $this->disconnectAction(),
            ])
            ->emptyStateHeading(__('calendar.connections.empty_title'))
            ->emptyStateDescription(__('calendar.connections.empty_body'))
            ->emptyStateIcon(Heroicon::OutlinedArrowsRightLeft);
    }

    /**
     * Azioni di intestazione: connessione demo (attiva) e i placeholder disabilitati
     * dei provider reali (Google/CalDAV, Cal-5/6).
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->connectDemoAction(),
            $this->connectGoogleAction(),
            $this->connectCalDavAction(),
        ];
    }

    /**
     * Crea una connessione DIMOSTRATIVA (provider log) per il tenant corrente. È l'unica
     * connessione utilizzabile senza credenziali: rende la pipeline di push provabile
     * subito. Autorizzata via policy (create); l'ente è sempre il tenant risolto.
     */
    private function connectDemoAction(): Action
    {
        return Action::make('connectDemo')
            ->label(__('calendar.connections.connect_demo'))
            ->icon(Heroicon::OutlinedPlus)
            ->authorize(fn (): bool => Auth::user()?->can('create', CalendarConnection::class) === true)
            ->requiresConfirmation()
            ->modalHeading(__('calendar.connections.connect_demo_heading'))
            ->modalDescription(__('calendar.connections.connect_demo_body'))
            ->modalSubmitActionLabel(__('calendar.connections.connect_demo_submit'))
            ->action(function (): void {
                $this->createDemoConnection();
            });
    }

    /**
     * Placeholder del flusso Google (Cal-5): visibile ma DISABILITATO, con avviso.
     */
    private function connectGoogleAction(): Action
    {
        return Action::make('connectGoogle')
            ->label(__('calendar.connections.connect_google'))
            ->icon(Heroicon::OutlinedCalendarDays)
            ->color('gray')
            ->disabled()
            ->tooltip(__('calendar.connections.coming_soon'));
    }

    /**
     * Placeholder del flusso CalDAV (Cal-6): visibile ma DISABILITATO, con avviso.
     */
    private function connectCalDavAction(): Action
    {
        return Action::make('connectCalDav')
            ->label(__('calendar.connections.connect_caldav'))
            ->icon(Heroicon::OutlinedCalendarDays)
            ->color('gray')
            ->disabled()
            ->tooltip(__('calendar.connections.coming_soon'));
    }

    /**
     * Disconnessione: elimina la connessione (cascade delle mappature
     * appointment_external_events). Gated dalla policy (delete): CalendarManage +
     * membership verificata dell'ente proprietario — il volontario è respinto.
     */
    private function disconnectAction(): Action
    {
        return Action::make('disconnect')
            ->label(__('calendar.connections.disconnect'))
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->authorize(fn (CalendarConnection $record): bool => Auth::user()?->can('delete', $record) === true)
            ->requiresConfirmation()
            ->modalHeading(__('calendar.connections.disconnect_heading'))
            ->modalDescription(__('calendar.connections.disconnect_body'))
            ->modalSubmitActionLabel(__('calendar.connections.disconnect_submit'))
            ->action(function (CalendarConnection $record): void {
                // Difesa in profondità: la policy è già valutata da authorize(), ma la
                // ri-verifichiamo prima di mutare (la UI non è il confine).
                $this->authorize('delete', $record);

                $record->delete();

                Notification::make()
                    ->success()
                    ->title(__('calendar.connections.disconnect_done'))
                    ->send();
            });
    }

    /**
     * Crea la connessione demo (provider log) sul tenant corrente, ri-verificando la
     * capacità di creazione lato server prima di scrivere.
     */
    private function createDemoConnection(): void
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Organization
            || Auth::user()?->can('create', CalendarConnection::class) !== true) {
            return;
        }

        CalendarConnection::query()->create([
            'organization_id' => $tenant->getKey(),
            'provider' => CalendarProvider::Log,
            'name' => __('calendar.connections.connect_demo_name'),
            'direction' => CalendarSyncDirection::Both,
            'status' => CalendarConnectionStatus::Active,
        ]);

        Notification::make()
            ->success()
            ->title(__('calendar.connections.connect_demo_done'))
            ->send();
    }
}
