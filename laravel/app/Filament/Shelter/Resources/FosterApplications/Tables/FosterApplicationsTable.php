<?php

declare(strict_types=1);

namespace App\Filament\Shelter\Resources\FosterApplications\Tables;

use App\Actions\Fostering\AdvanceFosterApplication;
use App\Actions\Fostering\EndFosterPlacement;
use App\Actions\Fostering\StartFosterPlacement;
use App\Enums\FosterApplicationStatus;
use App\Enums\FosterPlacementStatus;
use App\Enums\FosterPlacementType;
use App\Exceptions\AnimalAlreadyInFoster;
use App\Exceptions\CannotEndFosterPlacement;
use App\Exceptions\InvalidAnimalStatusTransition;
use App\Exceptions\InvalidFosterApplicationTransition;
use App\Filament\Shelter\Resources\FosterApplications\FosterApplicationResource;
use App\Filament\Shelter\Resources\FosterApplications\Support\StaffFosterTransitions;
use App\Models\FosterApplication;
use App\Models\Organization;
use App\Models\User;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Tabella delle richieste di affido del pannello shelter.
 *
 * Specchio di AdoptionApplicationsTable. La tabella è sottile: colonne + azioni.
 * NESSUNA logica di transizione qui. Ogni azione DELEGA a un'Action di
 * App\Actions\Fostering, che è l'unica fonte di verità del ciclo di vita (guardie,
 * activity log, notifiche). Le eccezioni di dominio (InvalidFosterApplicationTransition,
 * AnimalAlreadyInFoster, CannotEndFosterPlacement, InvalidAnimalStatusTransition)
 * vengono intercettate e mostrate come notifica danger, senza propagare (nessun
 * crash, stato invariato).
 *
 * Autorizzazione: tutte le azioni di gestione sono ->authorize('update'), che
 * risolve FosterApplicationPolicy::update sul record (FosterApplicationManage +
 * membership verificata). Il volontario (solo FosterApplicationView) non le vede.
 * L'assegnazione è un semplice update di attributo (non un cambio di stato del
 * dominio), quindi resta inline; i candidati sono i SOLI membri verificati del
 * tenant corrente.
 */
class FosterApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('animal.name')
                    ->label(__('fostering.shelter.animal'))
                    ->searchable()
                    ->sortable()
                    // Link diretto alla view della richiesta per la revisione;
                    // l'URL della Resource è già tenant-scoped.
                    ->url(fn (FosterApplication $record): string => FosterApplicationResource::getUrl('view', ['record' => $record])),
                TextColumn::make('user.name')
                    ->label(__('fostering.shelter.carer'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('fostering.shelter.status'))
                    ->badge()
                    // Il cast espone lo stato astratto: usiamo l'enum di dominio
                    // (statusEnum) come unica fonte di label/color del badge.
                    ->state(fn (FosterApplication $record): FosterApplicationStatus => $record->statusEnum())
                    ->sortable(),
                TextColumn::make('assignedTo.name')
                    ->label(__('fostering.shelter.assigned_to'))
                    ->placeholder(__('fostering.shelter.unassigned'))
                    ->toggleable(),
                TextColumn::make('submitted_at')
                    ->label(__('fostering.shelter.submitted_at'))
                    ->date()
                    ->sortable(),
                TextColumn::make('placement.type')
                    ->label(__('fostering.shelter.placement_type'))
                    ->badge()
                    ->formatStateUsing(fn (FosterApplication $record): ?string => $record->placement?->type->getLabel())
                    ->color(fn (FosterApplication $record): ?string => $record->placement?->type->getColor())
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('placement.start_date')
                    ->label(__('fostering.shelter.placement_start'))
                    ->date()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('placement.end_date')
                    ->label(__('fostering.shelter.placement_end'))
                    ->date()
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->defaultSort('submitted_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('fostering.shelter.status'))
                    ->options(FosterApplicationStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
                self::advanceAction(),
                self::startPlacementAction(),
                self::endPlacementAction(),
                self::assignAction(),
                self::editNotesAction(),
            ])
            ->emptyStateHeading(__('fostering.shelter.empty_title'))
            ->emptyStateDescription(__('fostering.shelter.empty_body'));
    }

    /**
     * Azione "Cambia stato": offre SOLO le transizioni di revisione/decisione
     * legali per lo staff nello stato corrente (Submitted/UnderReview/Waitlisted) e
     * delega la mutazione ad AdvanceFosterApplication. Sugli esiti decisionali
     * (Approvata/Respinta/Lista d'attesa) raccoglie una motivazione facoltativa.
     * Nessuna logica di transizione qui: raccolta input + delega + traduzione delle
     * eccezioni di dominio in notifiche.
     */
    public static function advanceAction(): Action
    {
        return Action::make('advance')
            ->label(__('fostering.shelter.advance'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->modalHeading(__('fostering.shelter.advance_heading'))
            ->modalSubmitActionLabel(__('fostering.shelter.advance_submit'))
            // Autorizzazione delegata alla Policy (update): FosterApplicationManage +
            // membership verificata. Il volontario non la vede.
            ->authorize('update')
            // Visibile solo se esistono transizioni staff dallo stato corrente
            // (gli stati senza uscita staff la nascondono: Approved usa "Avvia
            // affido", Active usa "Concludi affido").
            ->visible(fn (FosterApplication $record): bool => StaffFosterTransitions::forStatus($record->statusEnum()) !== [])
            ->schema([
                Select::make('status')
                    ->label(__('fostering.shelter.new_status'))
                    ->options(fn (FosterApplication $record): array => StaffFosterTransitions::options($record->statusEnum()))
                    ->required()
                    ->native(false)
                    ->live(),
                Textarea::make('reason')
                    ->label(__('fostering.shelter.reason'))
                    ->helperText(__('fostering.shelter.reason_help'))
                    ->rows(3)
                    ->maxLength(2000)
                    // La motivazione ha senso solo per gli esiti decisionali:
                    // reagisce al target scelto (Select ->live()).
                    ->visible(fn (Get $get): bool => self::isDecisionTarget($get('status'))),
            ])
            ->action(function (FosterApplication $record, array $data): void {
                $target = FosterApplicationStatus::from((string) $data['status']);
                $reason = self::isDecisionTarget($data['status'])
                    ? (($data['reason'] ?? null) !== null ? (string) $data['reason'] : null)
                    : null;

                try {
                    app(AdvanceFosterApplication::class)->handle(
                        application: $record,
                        to: $target,
                        actor: self::actor(),
                        reason: $reason,
                    );
                } catch (InvalidFosterApplicationTransition $exception) {
                    // Difesa: offriamo solo transizioni legali, ma una guardia può
                    // scattare a runtime. La notifichiamo senza propagare: nessun
                    // crash, stato invariato.
                    self::notifyFailure(__('fostering.shelter.advance_failed'), $exception->getMessage());

                    return;
                }

                self::notifySuccess(
                    __('fostering.shelter.advanced'),
                    __('fostering.shelter.advanced_body', ['status' => $target->getLabel()]),
                );
            });
    }

    /**
     * Azione "Avvia affido": disponibile SOLO su una richiesta APPROVATA, gated su
     * update (FosterApplicationManage). Raccoglie tipo, date e note e delega a
     * StartFosterPlacement, che è l'unica fonte di verità dell'avvio (crea il
     * FosterPlacement, porta l'animale a Fostered, notifica). Le eccezioni di
     * dominio vengono intercettate e mostrate come notifica danger, senza propagare.
     */
    public static function startPlacementAction(): Action
    {
        return Action::make('startPlacement')
            ->label(__('fostering.shelter.start_placement'))
            ->icon(Heroicon::OutlinedHome)
            ->color('success')
            ->modalHeading(__('fostering.shelter.start_placement_heading'))
            ->modalDescription(__('fostering.shelter.start_placement_help'))
            ->modalSubmitActionLabel(__('fostering.shelter.start_placement_submit'))
            ->authorize('update')
            // Solo su una richiesta Approved: negli altri stati l'avvio non ha senso.
            ->visible(fn (FosterApplication $record): bool => $record->statusEnum() === FosterApplicationStatus::Approved)
            ->schema([
                Select::make('type')
                    ->label(__('fostering.shelter.placement_type'))
                    ->options(FosterPlacementType::class)
                    ->required()
                    ->native(false),
                DatePicker::make('start_date')
                    ->label(__('fostering.shelter.placement_start'))
                    ->default(now())
                    ->required(),
                DatePicker::make('end_date')
                    ->label(__('fostering.shelter.placement_end'))
                    ->helperText(__('fostering.shelter.placement_end_help'))
                    ->afterOrEqual('start_date'),
                Textarea::make('notes')
                    ->label(__('fostering.shelter.placement_notes'))
                    ->rows(3)
                    ->maxLength(2000),
            ])
            ->action(function (FosterApplication $record, array $data): void {
                // Il Select con ->options(Enum::class) può restituire già l'istanza
                // enum oppure il suo value string: normalizziamo verso l'enum.
                $rawType = $data['type'];
                $type = $rawType instanceof FosterPlacementType
                    ? $rawType
                    : FosterPlacementType::from((string) $rawType);

                try {
                    app(StartFosterPlacement::class)->handle(
                        application: $record,
                        actor: self::actor(),
                        type: $type,
                        startDate: self::toDate($data['start_date'] ?? null),
                        endDate: self::toDate($data['end_date'] ?? null),
                        notes: self::toNullableString($data['notes'] ?? null),
                    );
                } catch (InvalidFosterApplicationTransition|AnimalAlreadyInFoster|InvalidAnimalStatusTransition $exception) {
                    self::notifyFailure(__('fostering.shelter.start_placement_failed'), $exception->getMessage());

                    return;
                }

                self::notifySuccess(
                    __('fostering.shelter.placement_started'),
                    __('fostering.shelter.placement_started_body', ['animal' => $record->animal->name]),
                );
            });
    }

    /**
     * Azione "Concludi affido": disponibile SOLO quando l'affido è in corso
     * (richiesta Active con un placement Active), gated su update. Raccoglie la data
     * di fine (default oggi) e le note e delega a EndFosterPlacement, che chiude
     * l'affido, completa la richiesta e riporta l'animale disponibile. L'affido
     * attivo è risolto dalla relazione `placement`. Le eccezioni di dominio vengono
     * intercettate e mostrate come notifica danger, senza propagare.
     */
    public static function endPlacementAction(): Action
    {
        return Action::make('endPlacement')
            ->label(__('fostering.shelter.end_placement'))
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('warning')
            ->modalHeading(__('fostering.shelter.end_placement_heading'))
            ->modalDescription(__('fostering.shelter.end_placement_help'))
            ->modalSubmitActionLabel(__('fostering.shelter.end_placement_submit'))
            ->authorize('update')
            // Solo quando l'affido è in corso: richiesta Active con placement Active.
            ->visible(fn (FosterApplication $record): bool => self::hasActivePlacement($record))
            ->schema([
                DatePicker::make('end_date')
                    ->label(__('fostering.shelter.placement_end'))
                    ->default(now())
                    ->required(),
                Textarea::make('notes')
                    ->label(__('fostering.shelter.placement_notes'))
                    ->rows(3)
                    ->maxLength(2000),
            ])
            ->action(function (FosterApplication $record, array $data): void {
                $placement = $record->placement;

                // Backstop: l'azione è visibile solo con un affido attivo, ma se lo
                // stato è cambiato nel frattempo notifichiamo senza propagare.
                if ($placement === null || $placement->status !== FosterPlacementStatus::Active) {
                    self::notifyFailure(
                        __('fostering.shelter.end_placement_failed'),
                        __('fostering.shelter.no_active_placement'),
                    );

                    return;
                }

                try {
                    app(EndFosterPlacement::class)->handle(
                        placement: $placement,
                        actor: self::actor(),
                        endDate: self::toDate($data['end_date'] ?? null),
                        notes: self::toNullableString($data['notes'] ?? null),
                    );
                } catch (CannotEndFosterPlacement|InvalidFosterApplicationTransition|InvalidAnimalStatusTransition $exception) {
                    self::notifyFailure(__('fostering.shelter.end_placement_failed'), $exception->getMessage());

                    return;
                }

                self::notifySuccess(
                    __('fostering.shelter.placement_ended'),
                    __('fostering.shelter.placement_ended_body', ['animal' => $record->animal->name]),
                );
            });
    }

    /**
     * Azione "Assegna": imposta assigned_to a un membro VERIFICATO del tenant
     * corrente. È un semplice update di attributo (non un cambio di stato del
     * dominio), quindi resta inline e minimale, non delega a un'Action. La lista dei
     * candidati è derivata da Organization::verifiedStaff del tenant, quindi un
     * non-membro non è selezionabile (isolamento per tenant).
     */
    public static function assignAction(): Action
    {
        return Action::make('assign')
            ->label(__('fostering.shelter.assign'))
            ->icon(Heroicon::OutlinedUserPlus)
            ->modalHeading(__('fostering.shelter.assign_heading'))
            ->modalSubmitActionLabel(__('fostering.shelter.assign_submit'))
            ->authorize('update')
            ->fillForm(fn (FosterApplication $record): array => [
                'assigned_to' => $record->assigned_to,
            ])
            ->schema([
                Select::make('assigned_to')
                    ->label(__('fostering.shelter.assign_member'))
                    // Solo i membri verificati del tenant corrente: nessun utente
                    // esterno all'ente può essere assegnato.
                    ->options(fn (): array => self::verifiedStaffOptions())
                    ->searchable()
                    ->native(false),
            ])
            ->action(function (FosterApplication $record, array $data): void {
                $assignee = $data['assigned_to'] ?? null;

                // Backstop di sicurezza: accetta solo un id presente tra i membri
                // verificati del tenant (la UI già lo garantisce).
                if ($assignee !== null && ! array_key_exists((int) $assignee, self::verifiedStaffOptions())) {
                    return;
                }

                $record->update(['assigned_to' => $assignee === null ? null : (int) $assignee]);

                self::notifySuccess(__('fostering.shelter.assign_done'));
            });
    }

    /**
     * Azione "Note interne": aggiorna staff_notes. Semplice update di attributo,
     * gated su update (FosterApplicationManage).
     */
    public static function editNotesAction(): Action
    {
        return Action::make('editNotes')
            ->label(__('fostering.shelter.notes'))
            ->icon(Heroicon::OutlinedPencilSquare)
            ->modalHeading(__('fostering.shelter.notes_heading'))
            ->modalSubmitActionLabel(__('fostering.shelter.notes_submit'))
            ->authorize('update')
            ->fillForm(fn (FosterApplication $record): array => [
                'staff_notes' => $record->staff_notes,
            ])
            ->schema([
                Textarea::make('staff_notes')
                    ->label(__('fostering.shelter.staff_notes'))
                    ->rows(5)
                    ->maxLength(5000),
            ])
            ->action(function (FosterApplication $record, array $data): void {
                $notes = ($data['staff_notes'] ?? null) !== null && $data['staff_notes'] !== ''
                    ? (string) $data['staff_notes']
                    : null;

                $record->update(['staff_notes' => $notes]);

                self::notifySuccess(__('fostering.shelter.notes_done'));
            });
    }

    /**
     * La richiesta ha un affido fisico in corso (Active con placement Active):
     * condizione per l'azione "Concludi affido".
     */
    private static function hasActivePlacement(FosterApplication $record): bool
    {
        return $record->statusEnum() === FosterApplicationStatus::Active
            && $record->placement?->status === FosterPlacementStatus::Active;
    }

    /**
     * Membri verificati del tenant corrente (id => nome), candidati per
     * l'assegnazione.
     *
     * @return array<int, string>
     */
    private static function verifiedStaffOptions(): array
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Organization) {
            return [];
        }

        /** @var array<int, string> $options */
        $options = $tenant->verifiedStaff()
            ->pluck('name', 'users.id')
            ->all();

        return $options;
    }

    /**
     * L'utente autenticato come attore della transizione (mai null nel pannello
     * autenticato).
     */
    private static function actor(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }

    /**
     * Normalizza il valore di un DatePicker verso un CarbonInterface (o null).
     */
    private static function toDate(mixed $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            return Carbon::parse($value);
        }

        return null;
    }

    /**
     * Normalizza un campo di testo facoltativo verso una stringa non vuota o null.
     */
    private static function toNullableString(mixed $value): ?string
    {
        return $value !== null && $value !== '' ? (string) $value : null;
    }

    /**
     * Il target scelto è un esito decisionale (motivazione pertinente)?
     */
    private static function isDecisionTarget(mixed $value): bool
    {
        if (! is_string($value) || $value === '') {
            return false;
        }

        $status = FosterApplicationStatus::tryFrom($value);

        return $status !== null && in_array($status, [
            FosterApplicationStatus::Approved,
            FosterApplicationStatus::Rejected,
            FosterApplicationStatus::Waitlisted,
        ], true);
    }

    private static function notifySuccess(string $title, ?string $body = null): void
    {
        $notification = Notification::make()
            ->title($title)
            ->success();

        if ($body !== null) {
            $notification->body($body);
        }

        $notification->send();
    }

    private static function notifyFailure(string $title, string $body): void
    {
        Notification::make()
            ->title($title)
            ->body($body)
            ->danger()
            ->send();
    }
}
