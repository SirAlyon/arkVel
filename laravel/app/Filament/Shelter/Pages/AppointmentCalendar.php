<?php

declare(strict_types=1);

namespace App\Filament\Shelter\Pages;

use App\Actions\Appointments\RequestAppointment;
use App\Actions\Appointments\RescheduleAppointment;
use App\Enums\AppointmentStatus;
use App\Enums\AppointmentType;
use App\Enums\PermissionName;
use App\Exceptions\AppointmentSlotUnavailable;
use App\Exceptions\CannotRescheduleAppointment;
use App\Exceptions\InvalidAppointmentSlot;
use App\Filament\Shelter\Resources\Appointments\Schemas\AppointmentInfolist;
use App\Filament\Shelter\Resources\Appointments\Support\StaffAppointmentActions;
use App\Models\AdoptionApplication;
use App\Models\Animal;
use App\Models\Appointment;
use App\Models\FosterApplication;
use App\Models\Organization;
use App\Models\User;
use App\Providers\CalendarAssetServiceProvider;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Calendario appuntamenti del pannello ente (shelter) — l'esperienza primaria
 * dell'agenda, in sostituzione della lista come vista principale.
 *
 * SCOPE (Cal-1 + Cal-2): lettura + gestione + INTERATTIVITÀ. Il calendario mostra gli
 * appuntamenti del tenant corrente e, al click su un evento, apre un modale con il
 * dettaglio (infolist) e le transizioni di staff (conferma / completa / assenza /
 * annulla). Cal-2 aggiunge lo spostamento drag-drop e la creazione da slot vuoto —
 * abilitati SOLO per chi può gestire ({@see canManageAppointments()}); il volontario
 * (sola vista) resta su un calendario read-only. Il sync esterno è slice successivi.
 *
 * Pagina SOTTILE: interroga (tenant-scoped) e delega. Nessuna business logic qui.
 *  - {@see fetchEvents()} è l'unico feed dati del calendario: filtra sempre per
 *    organization_id = tenant corrente (isolamento cross-tenant), sulla finestra
 *    [start, end] richiesta dal calendario.
 *  - {@see manageAction()} risolve il record tenant-scoped da $arguments['id'],
 *    autorizza (view) e RIUSA le stesse azioni di transizione della table
 *    ({@see StaffAppointmentActions}) come footer del modale: nessuna duplicazione
 *    della logica di ciclo di vita, che resta nelle Action di dominio.
 *  - {@see reschedule()} (drag-drop) e {@see createAction()} (slot vuoto) delegano a
 *    RescheduleAppointment / RequestAppointment: nessuna logica di dominio qui, solo
 *    risoluzione tenant-scoped, autorizzazione e mapping.
 *
 * Tenancy: come le Resource, la pagina è gated su membership verificata e opera nel
 * contesto del tenant risolto dal pannello. FullCalendar è caricato via
 * {@see CalendarAssetServiceProvider} (global build su CDN).
 */
class AppointmentCalendar extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected string $view = 'filament.shelter.pages.appointment-calendar';

    protected static ?string $slug = 'calendar';

    /**
     * Sopra la lista appuntamenti (nav sort 40): il calendario è ora la vista
     * primaria dell'agenda.
     */
    protected static ?int $navigationSort = 39;

    /**
     * Mappa dei colori (hex) degli eventi per stato dell'appuntamento. Rispecchia
     * l'intento semantico dei colori dell'enum ({@see AppointmentStatus::getColor()})
     * — gray/primary(emerald)/success/danger/warning — reso in esadecimale perché
     * FullCalendar colora gli eventi via CSS inline, non con i token del tema.
     *
     * @var array<value-of<AppointmentStatus>, string>
     */
    private const array STATUS_COLORS = [
        'requested' => '#6b7280', // gray-500  — in attesa
        'confirmed' => '#059669', // emerald-600 — primary del pannello
        'completed' => '#16a34a', // green-600 — success
        'cancelled' => '#dc2626', // red-600 — danger
        'no_show' => '#d97706',   // amber-600 — warning
    ];

    public static function getNavigationGroup(): ?string
    {
        return __('appointments.shelter.nav_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('appointments.calendar.nav');
    }

    public function getTitle(): string
    {
        return __('appointments.calendar.title');
    }

    /**
     * Accesso: mirror del gating delle Resource — l'utente deve poter vedere gli
     * appuntamenti (AppointmentPolicy::viewAny) ed essere un membro VERIFICATO del
     * tenant corrente. Lo scoping dei DATI è comunque garantito da fetchEvents()
     * (organization_id = tenant), qui filtriamo solo l'accesso alla pagina.
     */
    public static function canAccess(): bool
    {
        $user = Auth::user();
        $tenant = Filament::getTenant();

        if (! $user instanceof User || ! $tenant instanceof Organization) {
            return false;
        }

        return $user->can('viewAny', Appointment::class)
            && $user->isVerifiedMemberOf($tenant);
    }

    /**
     * L'utente corrente può GESTIRE gli appuntamenti del tenant (spostarli e crearli)?
     *
     * È il gate dell'interattività del calendario: drag-drop e create-by-click sono
     * abilitati SOLO quando true. Rispecchia AppointmentPolicy::update
     * (AppointmentManage + membership verificata del tenant): il volontario (solo
     * AppointmentView) ottiene un calendario di sola lettura. Sfrutta il record binding
     * per riga in fase di reschedule; qui è un gate a livello di UI, ricontrollato dalla
     * Policy su ogni azione server.
     */
    public function canManageAppointments(): bool
    {
        $user = Auth::user();
        $tenant = Filament::getTenant();

        if (! $user instanceof User || ! $tenant instanceof Organization) {
            return false;
        }

        return $user->can(PermissionName::AppointmentManage->value)
            && $user->isVerifiedMemberOf($tenant);
    }

    /**
     * Azioni di intestazione della pagina: gestione del feed iCal in sottoscrizione.
     * Visibili SOLO a chi può gestire ({@see canManageAppointments()}) — il volontario
     * (sola vista) non espone né rigenera il segreto del feed.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        if (! $this->canManageAppointments()) {
            return [];
        }

        return [
            $this->feedAction(),
        ];
    }

    /**
     * Mostra l'URL di sottoscrizione del feed iCal (sola lettura, copiabile) con la
     * spiegazione, e offre la rigenerazione/revoca del token come footer del modale.
     *
     * Il token è GENERATO ALLA PRIMA APERTURA ({@see Organization::ensureCalendarFeedToken()}):
     * il segreto nasce solo quando lo staff ne ha davvero bisogno. Autorizzata sulla
     * capacità di gestione (ri-verificata anche in getHeaderActions).
     */
    private function feedAction(): Action
    {
        return Action::make('calendarFeed')
            ->label(__('appointments.feed.action'))
            ->icon(Heroicon::OutlinedRss)
            ->authorize(fn (): bool => $this->canManageAppointments())
            ->modalHeading(__('appointments.feed.heading'))
            ->modalDescription(__('appointments.feed.description'))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('appointments.feed.close'))
            ->fillForm(fn (): array => ['feed_url' => $this->currentFeedUrl()])
            ->schema([
                TextInput::make('feed_url')
                    ->label(__('appointments.feed.url_label'))
                    ->readOnly()
                    ->helperText(__('appointments.feed.secret_notice')),
            ])
            ->extraModalFooterActions([
                Action::make('regenerateCalendarFeed')
                    ->label(__('appointments.feed.regenerate'))
                    ->color('danger')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->requiresConfirmation()
                    ->modalHeading(__('appointments.feed.regenerate_heading'))
                    ->modalDescription(__('appointments.feed.regenerate_body'))
                    ->modalSubmitActionLabel(__('appointments.feed.regenerate_submit'))
                    ->authorize(fn (): bool => $this->canManageAppointments())
                    ->action(function (): void {
                        $this->regenerateFeed();
                    }),
            ]);
    }

    /**
     * URL del feed del tenant corrente, generando il token alla prima richiesta.
     * Ritorna stringa vuota se manca il tenant (non dovrebbe accadere: la pagina è
     * gated su membership verificata).
     */
    private function currentFeedUrl(): string
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Organization) {
            return '';
        }

        $tenant->ensureCalendarFeedToken();

        return $tenant->calendarFeedUrl() ?? '';
    }

    /**
     * Rigenera il token del feed del tenant corrente (revoca il vecchio URL) e
     * notifica. Ricontrolla la capacità di gestione lato server prima di mutare.
     */
    private function regenerateFeed(): void
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Organization || ! $this->canManageAppointments()) {
            return;
        }

        $tenant->regenerateCalendarFeedToken();

        Notification::make()
            ->title(__('appointments.feed.regenerate_done'))
            ->success()
            ->send();
    }

    /**
     * Feed eventi del calendario per la finestra [start, end] richiesta dal client.
     *
     * ISOLAMENTO CROSS-TENANT: filtra esplicitamente per organization_id del tenant
     * corrente. Non si affida al global scope di Filament (che copre le Resource):
     * qui la query è diretta, quindi il vincolo è esplicito e testato. Restituisce
     * SOLO gli appuntamenti del tenant nella finestra, mai quelli di altri enti.
     *
     * @return list<array{
     *     id: int,
     *     title: string,
     *     start: string,
     *     end: string,
     *     color: string,
     *     extendedProps: array{status: string, type: string, animal: string, requester: string}
     * }>
     */
    public function fetchEvents(string $start, string $end): array
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Organization) {
            return [];
        }

        // La finestra arriva dal client (FullCalendar) ed è quindi input non fidato:
        // la parsiamo con Carbon e, se malformata, restituiamo un feed vuoto invece
        // di far esplodere la query. Il filtro organization_id resta comunque
        // applicato per primo: nessun input può aggirare l'isolamento del tenant.
        try {
            $from = Carbon::parse($start);
            $to = Carbon::parse($end);
        } catch (\Throwable) {
            return [];
        }

        return Appointment::query()
            ->where('organization_id', $tenant->getKey())
            // FullCalendar usa `end` ESCLUSIVO: [from, to) rispecchia il contratto del
            // client (l'ultimo giorno visibile è to-1), evitando eventi fuori griglia.
            ->where('scheduled_at', '>=', $from)
            ->where('scheduled_at', '<', $to)
            ->with(['animal', 'user'])
            ->get()
            ->map(fn (Appointment $appointment): array => $this->toEvent($appointment))
            ->all();
    }

    /**
     * Mappa un appuntamento nel formato evento di FullCalendar. Titolo localizzato
     * "{tipo}: {animale}"; fine = inizio + durata di slot del dominio
     * ({@see Appointment::SLOT_MINUTES}); colore per stato.
     *
     * @return array{
     *     id: int,
     *     title: string,
     *     start: string,
     *     end: string,
     *     color: string,
     *     extendedProps: array{status: string, type: string, animal: string, requester: string}
     * }
     */
    private function toEvent(Appointment $appointment): array
    {
        return [
            'id' => $appointment->getKey(),
            'title' => __('appointments.calendar.event_title', [
                'type' => $appointment->type->getLabel(),
                'animal' => $appointment->animal->name,
            ]),
            'start' => $appointment->scheduled_at->toIso8601String(),
            'end' => $appointment->scheduled_at->copy()->addMinutes(Appointment::SLOT_MINUTES)->toIso8601String(),
            'color' => self::STATUS_COLORS[$appointment->status->value],
            'extendedProps' => [
                'status' => $appointment->status->getLabel(),
                'type' => $appointment->type->getLabel(),
                'animal' => $appointment->animal->name,
                'requester' => $appointment->user->name,
            ],
        ];
    }

    /**
     * Azione "gestisci", montata da JS al click su un evento
     * (`$wire.mountAction('manage', { id })`). Risolve il record TENANT-SCOPED da
     * $arguments['id'] (404 se non appartiene al tenant), autorizza `view` e mostra
     * il dettaglio (infolist) nel modale.
     *
     * Le transizioni di staff sono RIUSATE dalla table
     * ({@see StaffAppointmentActions}) come footer del modale: ognuna porta con sé la
     * propria autorizzazione (update/cancel), la visibilità dal grafo dell'enum
     * (canTransitionTo) e l'intercettazione di InvalidAppointmentTransition. Dopo
     * un'azione, il calendario viene rinfrescato (evento browser `calendar-refresh`).
     */
    public function manageAction(): Action
    {
        return Action::make('manage')
            ->modalHeading(__('appointments.calendar.manage_heading'))
            ->record(fn (array $arguments): Appointment => $this->resolveTenantAppointment($arguments))
            ->authorize(fn (Appointment $record): bool => Auth::user()?->can('view', $record) === true)
            ->schema(fn (Schema $schema): Schema => AppointmentInfolist::configure($schema))
            // Modale di sola lettura: le mutazioni sono le footer action (transizioni).
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('appointments.calendar.close'))
            ->extraModalFooterActions(fn (array $arguments): array => $this->transitionFooterActions($arguments));
    }

    /**
     * Sposta un appuntamento (drag-drop lato client) al nuovo inizio $newStart.
     *
     * Chiamata da FullCalendar via `$wire.reschedule(id, startStr)`: risolve il record
     * TENANT-SCOPED (404 se non del tenant), autorizza `update` (AppointmentManage +
     * membership verificata: il volontario è respinto qui, non solo lato client),
     * parsifica l'orario e DELEGA a RescheduleAppointment.
     *
     * Ritorna un booleano per pilotare l'ottimismo del client: TRUE → il drag resta,
     * il calendario si rinfresca; FALSE → il client fa `info.revert()` riportando
     * l'evento all'orario precedente. Le eccezioni di dominio
     * (CannotRescheduleAppointment / InvalidAppointmentSlot / AppointmentSlotUnavailable)
     * sono intercettate e mostrate come notifica danger localizzata: nessun 500, stato
     * invariato, drag annullato.
     */
    public function reschedule(int $id, string $newStart): bool
    {
        $appointment = $this->resolveTenantAppointment(['id' => $id]);

        $this->authorize('update', $appointment);

        try {
            $when = Carbon::parse($newStart);
        } catch (\Throwable) {
            return false;
        }

        try {
            app(RescheduleAppointment::class)->handle($appointment, $this->actor(), $when);
        } catch (CannotRescheduleAppointment|InvalidAppointmentSlot|AppointmentSlotUnavailable $exception) {
            Notification::make()
                ->title(__('appointments.calendar.reschedule.failed'))
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return false;
        }

        Notification::make()
            ->title(__('appointments.calendar.reschedule.done'))
            ->success()
            ->send();

        $this->dispatch('calendar-refresh');

        return true;
    }

    /**
     * Azione "crea", montata da JS al select di uno slot vuoto
     * (`$wire.mountAction('create', { start })`). Raccoglie animale, richiedente, tipo,
     * note e data/ora (prefillata dallo slot selezionato) e DELEGA a RequestAppointment.
     *
     * Autorizzata sulla capacità di GESTIONE (canManageAppointments = AppointmentManage
     * + membership verificata), NON su AppointmentPolicy::create — che è volutamente
     * aperta per far richiedere una visita a qualunque adottante dal sito pubblico. Sul
     * calendario staff la creazione è un'azione operativa: il volontario (sola vista)
     * deve essere respinto anche lato server, non solo con la UI read-only.
     * Gli input sono SCOPED:
     *  - animale → solo animali del tenant corrente;
     *  - richiedente → SOLO persone GIÀ in relazione con l'ente (appuntamento o
     *    richiesta di adozione/affido esistente per questo ente). Mai la lista globale
     *    degli utenti (GDPR: nessuna esposizione di anagrafiche non pertinenti).
     *
     * Le eccezioni di dominio (slot nel passato / overlap) sono intercettate e mostrate
     * come notifica danger localizzata. Al successo si rinfresca il calendario.
     */
    public function createAction(): Action
    {
        return Action::make('create')
            ->modalHeading(__('appointments.calendar.create.heading'))
            ->modalSubmitActionLabel(__('appointments.calendar.create.submit'))
            ->authorize(fn (): bool => $this->canManageAppointments())
            ->fillForm(fn (array $arguments): array => [
                'scheduled_at' => isset($arguments['start']) ? Carbon::parse($arguments['start']) : null,
            ])
            ->schema([
                Select::make('animal_id')
                    ->label(__('appointments.calendar.create.animal'))
                    ->options(fn (): array => $this->tenantAnimalOptions())
                    ->searchable()
                    ->native(false)
                    ->required(),
                Select::make('user_id')
                    ->label(__('appointments.calendar.create.requester'))
                    ->helperText(__('appointments.calendar.create.requester_help'))
                    ->options(fn (): array => $this->relatedRequesterOptions())
                    ->searchable()
                    ->native(false)
                    ->required(),
                Select::make('type')
                    ->label(__('appointments.calendar.create.type'))
                    ->options(AppointmentType::class)
                    ->native(false)
                    ->required(),
                DateTimePicker::make('scheduled_at')
                    ->label(__('appointments.calendar.create.scheduled_at'))
                    ->seconds(false)
                    ->required(),
                Textarea::make('notes')
                    ->label(__('appointments.calendar.create.notes'))
                    ->rows(3)
                    ->maxLength(2000),
            ])
            ->action(function (array $data): void {
                $this->createAppointment($data);
            });
    }

    /**
     * Crea l'appuntamento delegando a RequestAppointment, dopo aver ri-validato che
     * animale e richiedente appartengano agli insiemi consentiti (backstop di sicurezza
     * oltre alla UI): un id fuori scope viene rifiutato senza toccare il DB.
     *
     * @param  array<string, mixed>  $data
     */
    private function createAppointment(array $data): void
    {
        $animalId = (int) ($data['animal_id'] ?? 0);
        $userId = (int) ($data['user_id'] ?? 0);

        // Backstop: solo animali del tenant e richiedenti già in relazione con l'ente.
        if (! array_key_exists($animalId, $this->tenantAnimalOptions())
            || ! array_key_exists($userId, $this->relatedRequesterOptions())) {
            Notification::make()
                ->title(__('appointments.calendar.create.failed'))
                ->danger()
                ->send();

            return;
        }

        $type = $data['type'] instanceof AppointmentType
            ? $data['type']
            : AppointmentType::from((string) $data['type']);

        $notes = ($data['notes'] ?? null) !== null && $data['notes'] !== ''
            ? (string) $data['notes']
            : null;

        try {
            app(RequestAppointment::class)->handle(
                User::query()->findOrFail($userId),
                Animal::query()->findOrFail($animalId),
                $type,
                Carbon::parse((string) $data['scheduled_at']),
                $notes,
            );
        } catch (InvalidAppointmentSlot|AppointmentSlotUnavailable $exception) {
            Notification::make()
                ->title(__('appointments.calendar.create.failed'))
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title(__('appointments.calendar.create.done'))
            ->success()
            ->send();

        $this->dispatch('calendar-refresh');
    }

    /**
     * Animali del tenant corrente come opzioni id → nome per il Select di creazione.
     * Scoping esplicito a organization_id = tenant: nessuna fuga cross-tenant.
     *
     * @return array<int, string>
     */
    private function tenantAnimalOptions(): array
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Organization) {
            return [];
        }

        return Animal::query()
            ->where('organization_id', $tenant->getKey())
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Richiedenti selezionabili in creazione: SOLO utenti GIÀ in relazione con il
     * tenant corrente — hanno un appuntamento, una richiesta di adozione o una
     * richiesta di affido per questo ente. La lista globale degli utenti NON è mai
     * esposta (GDPR: minimizzazione, niente anagrafiche non pertinenti).
     *
     * @return array<int, string>
     */
    private function relatedRequesterOptions(): array
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Organization) {
            return [];
        }

        $organizationId = $tenant->getKey();

        // Union degli user_id già in relazione con l'ente: chi ha un appuntamento, una
        // richiesta di adozione o una richiesta di affido per questo tenant. Query
        // dirette per user_id/organization_id (nessuna relazione dedicata sul model
        // User): un utente estraneo all'ente non compare nella lista.
        $relatedUserIds = Appointment::query()
            ->where('organization_id', $organizationId)
            ->distinct()
            ->pluck('user_id')
            ->merge(
                AdoptionApplication::query()
                    ->where('organization_id', $organizationId)
                    ->distinct()
                    ->pluck('user_id'),
            )
            ->merge(
                FosterApplication::query()
                    ->where('organization_id', $organizationId)
                    ->distinct()
                    ->pluck('user_id'),
            )
            ->unique()
            ->all();

        return User::query()
            ->whereIn('id', $relatedUserIds)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * L'utente autenticato come attore delle azioni (mai null nel pannello autenticato).
     */
    private function actor(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    /**
     * Le transizioni di staff come footer del modale, RIUSATE dalla table
     * ({@see StaffAppointmentActions}) — nessuna duplicazione della logica di ciclo di
     * vita, che resta nelle Action di dominio.
     *
     * Ogni azione riceve il record COME MODELLO CONCRETO, risolto una volta dagli
     * argomenti del modale parent (tenant-scoped). Senza record,
     * visibilità/autorizzazione girerebbero a vuoto, aggirando la Policy. Dopo
     * l'esecuzione si rinfresca il calendario (`calendar-refresh`).
     *
     * NOTA sul nome dell'annulla: la transizione condivisa si chiama `cancel`, ma nel
     * footer del modale la chiave `cancel` è riservata da Filament al pulsante di
     * chiusura del modale (che la sovrascriverebbe). La rinominiamo qui in
     * `cancelAppointment` per non collidere: la logica delegata resta identica.
     *
     * @param  array<string, mixed>  $arguments
     * @return list<Action>
     */
    private function transitionFooterActions(array $arguments): array
    {
        if (! isset($arguments['id'])) {
            return [];
        }

        $appointment = $this->resolveTenantAppointment($arguments);

        $transitions = array_map(
            fn (Action $action): Action => $action
                ->record($appointment)
                ->after(fn () => $this->dispatch('calendar-refresh')),
            [
                StaffAppointmentActions::confirmAction(),
                StaffAppointmentActions::completeAction(),
                StaffAppointmentActions::noShowAction(),
                StaffAppointmentActions::cancelAction()->name('cancelAppointment'),
            ],
        );

        // Download .ics del singolo appuntamento: link alla rotta gated da Policy
        // (view) — lo staff verificato del tenant è autorizzato. Apre in nuova scheda
        // (download del file), non una transizione: nessun after-refresh.
        $transitions[] = Action::make('icsDownload')
            ->label(__('appointments.ics.download'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->url(route('appointments.ics', $appointment))
            ->openUrlInNewTab();

        return $transitions;
    }

    /**
     * Risolve l'appuntamento vincolandolo al tenant corrente: un id di un altro ente
     * (o inesistente) solleva 404 (findOrFail sulla query tenant-scoped). È la stessa
     * garanzia del record binding tenant-scoped delle Resource.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function resolveTenantAppointment(array $arguments): Appointment
    {
        $tenant = Filament::getTenant();

        abort_unless($tenant instanceof Organization, 404);

        return Appointment::query()
            ->where('organization_id', $tenant->getKey())
            ->with(['animal', 'user'])
            ->findOrFail($arguments['id']);
    }
}
