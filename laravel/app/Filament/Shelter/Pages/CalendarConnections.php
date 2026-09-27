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
use App\Support\Calendar\CalDav\CalDavClient;
use App\Support\Calendar\Google\GoogleOAuth;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
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
 * che rende la pipeline di push dimostrabile subito. Google (Cal-5) si collega via OAuth
 * quando configurato; CalDAV (Cal-6) via form (url + username + app-password) sempre
 * abilitato, per-connessione, con verifica preventiva della raggiungibilità.
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
     * Traduce i flash del callback OAuth ({@see GoogleCalendarConnectController}) in
     * notifiche Filament: l'utente torna qui dal consenso Google e vede l'esito.
     */
    public function mount(): void
    {
        $success = session('calendar_success');

        if (is_string($success)) {
            Notification::make()->success()->title($success)->send();
        }

        $error = session('calendar_error');

        if (is_string($error)) {
            Notification::make()->danger()->title($error)->send();
        }
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
     * Azioni di intestazione: connessione demo (attiva), Google (OAuth, config-gated) e
     * CalDAV (form con verifica, sempre abilitato).
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
     * Connessione Google Calendar (Cal-5): avvia il flusso OAuth quando l'installazione è
     * configurata ({@see GoogleOAuth::configured()}); altrimenti resta DISABILITATA con un
     * avviso localizzato che invita a impostare le variabili GOOGLE_CALENDAR_*. Gated
     * comunque dalla policy (create) + tenant risolto.
     */
    private function connectGoogleAction(): Action
    {
        $configured = GoogleOAuth::configured();

        $action = Action::make('connectGoogle')
            ->label(__('calendar.connections.connect_google'))
            ->icon(Heroicon::OutlinedCalendarDays)
            ->authorize(fn (): bool => Auth::user()?->can('create', CalendarConnection::class) === true);

        if (! $configured) {
            return $action
                ->color('gray')
                ->disabled()
                ->tooltip(__('calendar.connections.google.not_configured'));
        }

        return $action
            ->color('primary')
            ->url(function (): ?string {
                $tenant = Filament::getTenant();

                return $tenant instanceof Organization
                    ? route('calendar.google.redirect', ['tenant' => $tenant->slug])
                    : null;
            });
    }

    /**
     * Connessione CalDAV (Cal-6): azione con form SEMPRE abilitata (nessun config-gating:
     * CalDAV è per-connessione, ogni ente inserisce il proprio server). Raccoglie url della
     * collezione + username + app-password; al submit VERIFICA la raggiungibilità con le
     * credenziali (PROPFIND) e, solo se ok, crea/aggiorna una connessione CalDAV cifrata
     * per il tenant corrente. Se la verifica fallisce → notifica danger, nessuna
     * connessione. Gated dalla policy (create) + tenant risolto.
     */
    private function connectCalDavAction(): Action
    {
        return Action::make('connectCalDav')
            ->label(__('calendar.connections.connect_caldav'))
            ->icon(Heroicon::OutlinedCalendarDays)
            ->color('primary')
            ->authorize(fn (): bool => Auth::user()?->can('create', CalendarConnection::class) === true)
            ->modalHeading(__('calendar.connections.caldav.heading'))
            ->modalDescription(__('calendar.connections.caldav.body'))
            ->modalSubmitActionLabel(__('calendar.connections.caldav.submit'))
            ->schema([
                TextInput::make('url')
                    ->label(__('calendar.connections.caldav.url'))
                    ->helperText(__('calendar.connections.caldav.url_help'))
                    ->url()
                    ->required()
                    ->maxLength(2048),
                TextInput::make('username')
                    ->label(__('calendar.connections.caldav.username'))
                    ->helperText(__('calendar.connections.caldav.username_help'))
                    ->required()
                    ->maxLength(255),
                TextInput::make('app_password')
                    ->label(__('calendar.connections.caldav.app_password'))
                    ->helperText(__('calendar.connections.caldav.app_password_help'))
                    ->password()
                    ->revealable()
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (array $data): void {
                $this->connectCalDav($data);
            });
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

    /**
     * Crea/aggiorna una connessione CalDAV per il tenant corrente dopo aver VERIFICATO
     * url + credenziali. Ri-verifica lato server la capacità di creazione (la UI non è il
     * confine) e la presenza del tenant. La verifica di raggiungibilità (PROPFIND con le
     * credenziali date) fallita → notifica danger, nessuna scrittura: mai persistere
     * credenziali non validate.
     *
     * Su successo: `updateOrCreate` per (tenant, CalDAV) — una sola connessione CalDAV per
     * ente, riconfigurabile — con `credentials` cifrato {url, username, app_password},
     * `external_calendar_id` = url della collezione, status Active, direzione Both. L'ente è
     * SEMPRE il tenant risolto: nessuna connessione può essere creata per un altro ente.
     *
     * @param  array<string, mixed>  $data
     */
    private function connectCalDav(array $data): void
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Organization
            || Auth::user()?->can('create', CalendarConnection::class) !== true) {
            return;
        }

        $url = is_string($data['url'] ?? null) ? trim($data['url']) : '';
        $username = is_string($data['username'] ?? null) ? trim($data['username']) : '';
        $appPassword = is_string($data['app_password'] ?? null) ? $data['app_password'] : '';

        // Verifica di raggiungibilità con le credenziali fornite PRIMA di persistere:
        // niente credenziali non validate nel DB.
        if (! app(CalDavClient::class)->verify($url, $username, $appPassword)) {
            Notification::make()
                ->danger()
                ->title(__('calendar.connections.caldav.verify_failed'))
                ->send();

            return;
        }

        CalendarConnection::query()->updateOrCreate(
            [
                'organization_id' => $tenant->getKey(),
                'provider' => CalendarProvider::CalDav,
            ],
            [
                'name' => __('calendar.connections.caldav.name'),
                'external_calendar_id' => $url,
                // Cifrato at-rest (cast encrypted:array): l'app-password non compare mai in
                // chiaro nel DB.
                'credentials' => [
                    'url' => $url,
                    'username' => $username,
                    'app_password' => $appPassword,
                ],
                'direction' => CalendarSyncDirection::Both,
                'status' => CalendarConnectionStatus::Active,
                'last_error' => null,
            ],
        );

        Notification::make()
            ->success()
            ->title(__('calendar.connections.caldav.connected'))
            ->send();
    }
}
