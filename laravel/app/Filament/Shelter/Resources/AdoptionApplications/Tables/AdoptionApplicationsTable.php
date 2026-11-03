<?php

declare(strict_types=1);

namespace App\Filament\Shelter\Resources\AdoptionApplications\Tables;

use App\Actions\Adoptions\FinalizeAdoption;
use App\Actions\Applications\AdvanceApplication;
use App\Enums\ApplicationStatus;
use App\Exceptions\AnimalAlreadyHasPendingAdoption;
use App\Exceptions\InvalidAnimalStatusTransition;
use App\Exceptions\InvalidApplicationTransition;
use App\Filament\Shelter\Resources\AdoptionApplications\AdoptionApplicationResource;
use App\Filament\Shelter\Resources\AdoptionApplications\Support\StaffTransitions;
use App\Models\AdoptionApplication;
use App\Models\Organization;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * Tabella delle candidature del pannello shelter.
 *
 * La tabella e' sottile: colonne + azioni. NESSUNA logica di transizione qui.
 * L'avanzamento di stato raccoglie il target scelto (ristretto alle transizioni
 * legali per lo staff nello stato corrente, {@see StaffTransitions}) e delega ad
 * App\Actions\Applications\AdvanceApplication, che e' l'unica fonte di verita del
 * ciclo di vita (guardie, activity log, notifica all'adottante). Le due
 * eccezioni di dominio (InvalidApplicationTransition,
 * AnimalAlreadyHasPendingAdoption) vengono intercettate e mostrate come
 * notifica danger, senza propagare (nessun crash, stato invariato).
 *
 * Autorizzazione: tutte le azioni di gestione sono ->authorize('update'), che
 * risolve AdoptionApplicationPolicy::update sul record (ApplicationManage +
 * membership verificata). Il volontario (solo ApplicationView) non le vede.
 * L'assegnazione e' un semplice update di attributo (non un cambio di stato del
 * dominio), quindi resta inline; i candidati sono i SOLI membri verificati del
 * tenant corrente.
 */
class AdoptionApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('animal.name')
                    ->label(__('applications.shelter.animal'))
                    ->searchable()
                    ->sortable()
                    // Link diretto alla view della candidatura per la revisione;
                    // l'URL della Resource e' gia' tenant-scoped.
                    ->url(fn (AdoptionApplication $record): string => AdoptionApplicationResource::getUrl('view', ['record' => $record])),
                TextColumn::make('user.name')
                    ->label(__('applications.shelter.adopter'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('applications.shelter.status'))
                    ->badge()
                    // Il cast espone lo stato astratto: usiamo l'enum di dominio
                    // (statusEnum) come unica fonte di label/color del badge.
                    ->state(fn (AdoptionApplication $record): ApplicationStatus => $record->statusEnum())
                    ->sortable(),
                TextColumn::make('assignedTo.name')
                    ->label(__('applications.shelter.assigned_to'))
                    ->placeholder(__('applications.shelter.unassigned'))
                    ->toggleable(),
                TextColumn::make('submitted_at')
                    ->label(__('applications.shelter.submitted_at'))
                    ->date()
                    ->sortable(),
            ])
            ->defaultSort('submitted_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label(__('applications.shelter.status'))
                    ->options(ApplicationStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
                self::advanceAction(),
                self::finalizeAction(),
                self::assignAction(),
                self::editNotesAction(),
            ])
            ->emptyStateHeading(__('applications.shelter.empty_title'))
            ->emptyStateDescription(__('applications.shelter.empty_body'));
    }

    /**
     * Azione "Cambia stato": offre SOLO le transizioni legali per lo staff nello
     * stato corrente e delega la mutazione ad AdvanceApplication. Sugli esiti
     * decisionali (Approvata/Respinta/Lista d'attesa) raccoglie una motivazione
     * facoltativa. Nessuna logica di transizione qui: raccolta input + delega +
     * traduzione delle eccezioni di dominio in notifiche.
     */
    public static function advanceAction(): Action
    {
        return Action::make('advance')
            ->label(__('applications.shelter.advance'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->modalHeading(__('applications.shelter.advance_heading'))
            ->modalSubmitActionLabel(__('applications.shelter.advance_submit'))
            // Autorizzazione delegata alla Policy (update): ApplicationManage +
            // membership verificata. Il volontario non la vede.
            ->authorize('update')
            // Visibile solo se esistono transizioni staff dallo stato corrente
            // (gli stati terminali/decisionali senza uscita staff la nascondono).
            ->visible(fn (AdoptionApplication $record): bool => StaffTransitions::forStatus($record->statusEnum()) !== [])
            ->schema([
                Select::make('status')
                    ->label(__('applications.shelter.new_status'))
                    ->options(fn (AdoptionApplication $record): array => StaffTransitions::options($record->statusEnum()))
                    ->required()
                    ->native(false)
                    ->live(),
                Textarea::make('reason')
                    ->label(__('applications.shelter.reason'))
                    ->helperText(__('applications.shelter.reason_help'))
                    ->rows(3)
                    ->maxLength(2000)
                    // La motivazione ha senso solo per gli esiti decisionali:
                    // reagisce al target scelto (Select ->live()).
                    ->visible(fn (Get $get): bool => self::isDecisionTarget($get('status'))),
            ])
            ->action(function (AdoptionApplication $record, array $data): void {
                $target = ApplicationStatus::from((string) $data['status']);
                $reason = self::isDecisionTarget($data['status'])
                    ? (($data['reason'] ?? null) !== null ? (string) $data['reason'] : null)
                    : null;

                try {
                    app(AdvanceApplication::class)->handle(
                        application: $record,
                        to: $target,
                        actor: self::actor(),
                        reason: $reason,
                    );
                } catch (InvalidApplicationTransition|AnimalAlreadyHasPendingAdoption $exception) {
                    // Difesa: offriamo solo transizioni legali, ma la guardia
                    // "una sola adozione attiva per animale" puo' scattare a
                    // runtime (race tra candidature dello stesso animale). La
                    // notifichiamo senza propagare: nessun crash, stato invariato.
                    Notification::make()
                        ->title(__('applications.shelter.advance_failed'))
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title(__('applications.shelter.advanced'))
                    ->body(__('applications.shelter.advanced_body', ['status' => $target->getLabel()]))
                    ->success()
                    ->send();
            });
    }

    /**
     * Azione "Finalizza adozione": disponibile SOLO su una candidatura in
     * AdoptionPending, gated su update (ApplicationManage). Raccoglie il
     * contributo (fee, facoltativo) e la valuta (default EUR) e delega ad
     * App\Actions\Adoptions\FinalizeAdoption, che è l'unica fonte di verità della
     * finalizzazione (crea l'Adozione, porta l'animale ad Adopted, chiude le
     * candidature sorelle, genera il contratto PDF, notifica). Le eccezioni di
     * dominio vengono intercettate e mostrate come notifica danger, senza
     * propagare (nessun crash, stato invariato).
     */
    public static function finalizeAction(): Action
    {
        return Action::make('finalize')
            ->label(__('adoptions.shelter.finalize'))
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->modalHeading(__('adoptions.shelter.finalize_heading'))
            ->modalDescription(__('adoptions.shelter.finalize_help'))
            ->modalSubmitActionLabel(__('adoptions.shelter.finalize_submit'))
            ->authorize('update')
            // Solo su una candidatura in AdoptionPending: negli altri stati la
            // finalizzazione non ha senso e l'azione resta nascosta.
            ->visible(fn (AdoptionApplication $record): bool => $record->statusEnum() === ApplicationStatus::AdoptionPending)
            ->schema([
                TextInput::make('fee')
                    ->label(__('adoptions.shelter.fee'))
                    ->helperText(__('adoptions.shelter.fee_help'))
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01),
                TextInput::make('currency')
                    ->label(__('adoptions.shelter.currency'))
                    ->default('EUR')
                    ->maxLength(3)
                    ->required(),
            ])
            ->action(function (AdoptionApplication $record, array $data): void {
                $fee = ($data['fee'] ?? null) !== null && $data['fee'] !== ''
                    ? (float) $data['fee']
                    : null;
                $currency = ($data['currency'] ?? null) !== null && $data['currency'] !== ''
                    ? strtoupper((string) $data['currency'])
                    : 'EUR';

                try {
                    app(FinalizeAdoption::class)->handle(
                        application: $record,
                        actor: self::actor(),
                        fee: $fee,
                        currency: $currency,
                    );
                } catch (InvalidApplicationTransition|InvalidAnimalStatusTransition $exception) {
                    // La finalizzazione tocca più stati (candidatura + animale):
                    // se una guardia scatta a runtime la notifichiamo senza
                    // propagare, lasciando tutto invariato (transazione rollback).
                    Notification::make()
                        ->title(__('adoptions.shelter.finalize_failed'))
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title(__('adoptions.shelter.finalized'))
                    ->body(__('adoptions.shelter.finalized_body', ['animal' => $record->animal->name]))
                    ->success()
                    ->send();
            });
    }

    /**
     * Azione "Assegna": imposta assigned_to a un membro VERIFICATO del tenant
     * corrente. E' un semplice update di attributo (non un cambio di stato del
     * dominio), quindi resta inline e minimale, non delega a un'Action. La lista
     * dei candidati e' derivata da Organization::verifiedStaff del tenant, quindi
     * un non-membro non e' selezionabile (isolamento per tenant).
     */
    public static function assignAction(): Action
    {
        return Action::make('assign')
            ->label(__('applications.shelter.assign'))
            ->icon(Heroicon::OutlinedUserPlus)
            ->modalHeading(__('applications.shelter.assign_heading'))
            ->modalSubmitActionLabel(__('applications.shelter.assign_submit'))
            ->authorize('update')
            ->fillForm(fn (AdoptionApplication $record): array => [
                'assigned_to' => $record->assigned_to,
            ])
            ->schema([
                Select::make('assigned_to')
                    ->label(__('applications.shelter.assign_member'))
                    // Solo i membri verificati del tenant corrente: nessun utente
                    // esterno all'ente puo' essere assegnato.
                    ->options(fn (): array => self::verifiedStaffOptions())
                    ->searchable()
                    ->native(false),
            ])
            ->action(function (AdoptionApplication $record, array $data): void {
                $assignee = $data['assigned_to'] ?? null;

                // Backstop di sicurezza: accetta solo un id presente tra i membri
                // verificati del tenant (la UI gia' lo garantisce).
                if ($assignee !== null && ! array_key_exists((int) $assignee, self::verifiedStaffOptions())) {
                    return;
                }

                $record->update(['assigned_to' => $assignee === null ? null : (int) $assignee]);

                Notification::make()
                    ->title(__('applications.shelter.assign_done'))
                    ->success()
                    ->send();
            });
    }

    /**
     * Azione "Note interne": aggiorna staff_notes. Semplice update di attributo,
     * gated su update (ApplicationManage).
     */
    public static function editNotesAction(): Action
    {
        return Action::make('editNotes')
            ->label(__('applications.shelter.notes'))
            ->icon(Heroicon::OutlinedPencilSquare)
            ->modalHeading(__('applications.shelter.notes_heading'))
            ->modalSubmitActionLabel(__('applications.shelter.notes_submit'))
            ->authorize('update')
            ->fillForm(fn (AdoptionApplication $record): array => [
                'staff_notes' => $record->staff_notes,
            ])
            ->schema([
                Textarea::make('staff_notes')
                    ->label(__('applications.shelter.staff_notes'))
                    ->rows(5)
                    ->maxLength(5000),
            ])
            ->action(function (AdoptionApplication $record, array $data): void {
                $notes = ($data['staff_notes'] ?? null) !== null && $data['staff_notes'] !== ''
                    ? (string) $data['staff_notes']
                    : null;

                $record->update(['staff_notes' => $notes]);

                Notification::make()
                    ->title(__('applications.shelter.notes_done'))
                    ->success()
                    ->send();
            });
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
     * Il target scelto e' un esito decisionale (motivazione pertinente)?
     */
    private static function isDecisionTarget(mixed $value): bool
    {
        if (! is_string($value) || $value === '') {
            return false;
        }

        $status = ApplicationStatus::tryFrom($value);

        return $status !== null && in_array($status, [
            ApplicationStatus::Approved,
            ApplicationStatus::Rejected,
            ApplicationStatus::Waitlisted,
        ], true);
    }
}
