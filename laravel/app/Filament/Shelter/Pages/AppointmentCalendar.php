<?php

declare(strict_types=1);

namespace App\Filament\Shelter\Pages;

use App\Enums\AppointmentStatus;
use App\Filament\Shelter\Resources\Appointments\Schemas\AppointmentInfolist;
use App\Filament\Shelter\Resources\Appointments\Support\StaffAppointmentActions;
use App\Models\Appointment;
use App\Models\Organization;
use App\Models\User;
use App\Providers\CalendarAssetServiceProvider;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Calendario appuntamenti del pannello ente (shelter) — l'esperienza primaria
 * dell'agenda, in sostituzione della lista come vista principale.
 *
 * SCOPE DI QUESTO SLICE (Cal-1): SOLA lettura + gestione. Il calendario mostra gli
 * appuntamenti del tenant corrente e, al click su un evento, apre un modale con il
 * dettaglio (infolist) e le transizioni di staff (conferma / completa / assenza /
 * annulla). NIENTE drag-drop né create-by-click (Cal-2), né sync esterno (slice
 * successivi): `editable: false` lato client.
 *
 * Pagina SOTTILE: interroga (tenant-scoped) e delega. Nessuna business logic qui.
 *  - {@see fetchEvents()} è l'unico feed dati del calendario: filtra sempre per
 *    organization_id = tenant corrente (isolamento cross-tenant), sulla finestra
 *    [start, end] richiesta dal calendario.
 *  - {@see manageAction()} risolve il record tenant-scoped da $arguments['id'],
 *    autorizza (view) e RIUSA le stesse azioni di transizione della table
 *    ({@see StaffAppointmentActions}) come footer del modale: nessuna duplicazione
 *    della logica di ciclo di vita, che resta nelle Action di dominio.
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

        return array_map(
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
