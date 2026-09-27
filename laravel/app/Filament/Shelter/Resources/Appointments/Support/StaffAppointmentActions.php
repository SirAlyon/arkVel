<?php

declare(strict_types=1);

namespace App\Filament\Shelter\Resources\Appointments\Support;

use App\Actions\Appointments\CancelAppointment;
use App\Actions\Appointments\CompleteAppointment;
use App\Actions\Appointments\ConfirmAppointment;
use App\Actions\Appointments\MarkAppointmentNoShow;
use App\Enums\AppointmentStatus;
use App\Exceptions\InvalidAppointmentTransition;
use App\Models\Appointment;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

/**
 * Azioni di gestione degli appuntamenti offerte allo STAFF dell'ente nell'UI
 * (table + header della view). Specchio di StaffFosterTransitions/FosterApplicationsTable.
 *
 * Ogni azione è DISCRETA e nominata (Conferma / Completa / Assenza / Annulla),
 * NON un Select generico: il grafo dell'appuntamento è piccolo e le transizioni
 * hanno una semantica chiara che merita un pulsante dedicato. Nessuna logica di
 * transizione qui: ogni azione DELEGA a un'Action di App\Actions\Appointments, che
 * è l'unica fonte di verità del ciclo di vita (guardia canTransitionTo, activity
 * log, notifiche).
 *
 * Autorizzazione:
 *  - Confirm / Complete / No-show → ->authorize('update') (AppointmentManage +
 *    membership verificata). Il volontario (solo AppointmentView) non le vede.
 *  - Cancel → ->authorize('cancel') (staff-manage OPPURE proprietario; qui è
 *    sempre staff).
 *
 * Visibilità: derivata dal grafo dell'enum ($status->canTransitionTo(...)), così
 * ogni pulsante appare solo quando la transizione è legale. La guardia di dominio
 * viene comunque ri-valutata nell'Action; se scatta a runtime,
 * InvalidAppointmentTransition è intercettata e mostrata come notifica danger,
 * senza propagare (nessun 500, stato invariato).
 */
final class StaffAppointmentActions
{
    /**
     * Azione "Conferma": visibile solo su un appuntamento Richiesto (Requested →
     * Confirmed legale), gated su update. Delega a ConfirmAppointment.
     */
    public static function confirmAction(): Action
    {
        return Action::make('confirm')
            ->label(__('appointments.shelter.confirm'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('primary')
            ->requiresConfirmation()
            ->modalHeading(__('appointments.shelter.confirm_heading'))
            ->modalDescription(__('appointments.shelter.confirm_body'))
            ->modalSubmitActionLabel(__('appointments.shelter.confirm_submit'))
            ->authorize('update')
            ->visible(fn (Appointment $record): bool => $record->status->canTransitionTo(AppointmentStatus::Confirmed))
            ->action(function (Appointment $record): void {
                try {
                    app(ConfirmAppointment::class)->handle($record, self::actor());
                } catch (InvalidAppointmentTransition $exception) {
                    self::notifyFailure(__('appointments.shelter.confirm_failed'), $exception->getMessage());

                    return;
                }

                self::notifySuccess(__('appointments.shelter.confirm_done'));
            });
    }

    /**
     * Azione "Completa": visibile solo su un appuntamento Confermato (Confirmed →
     * Completed legale), gated su update. Delega a CompleteAppointment.
     */
    public static function completeAction(): Action
    {
        return Action::make('complete')
            ->label(__('appointments.shelter.complete'))
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading(__('appointments.shelter.complete_heading'))
            ->modalDescription(__('appointments.shelter.complete_body'))
            ->modalSubmitActionLabel(__('appointments.shelter.complete_submit'))
            ->authorize('update')
            ->visible(fn (Appointment $record): bool => $record->status->canTransitionTo(AppointmentStatus::Completed))
            ->action(function (Appointment $record): void {
                try {
                    app(CompleteAppointment::class)->handle($record, self::actor());
                } catch (InvalidAppointmentTransition $exception) {
                    self::notifyFailure(__('appointments.shelter.complete_failed'), $exception->getMessage());

                    return;
                }

                self::notifySuccess(__('appointments.shelter.complete_done'));
            });
    }

    /**
     * Azione "Assenza": visibile solo su un appuntamento Confermato (Confirmed →
     * NoShow legale), gated su update. Delega a MarkAppointmentNoShow.
     */
    public static function noShowAction(): Action
    {
        return Action::make('noShow')
            ->label(__('appointments.shelter.no_show'))
            ->icon(Heroicon::OutlinedUserMinus)
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading(__('appointments.shelter.no_show_heading'))
            ->modalDescription(__('appointments.shelter.no_show_body'))
            ->modalSubmitActionLabel(__('appointments.shelter.no_show_submit'))
            ->authorize('update')
            ->visible(fn (Appointment $record): bool => $record->status->canTransitionTo(AppointmentStatus::NoShow))
            ->action(function (Appointment $record): void {
                try {
                    app(MarkAppointmentNoShow::class)->handle($record, self::actor());
                } catch (InvalidAppointmentTransition $exception) {
                    self::notifyFailure(__('appointments.shelter.no_show_failed'), $exception->getMessage());

                    return;
                }

                self::notifySuccess(__('appointments.shelter.no_show_done'));
            });
    }

    /**
     * Azione "Annulla": visibile su Richiesto o Confermato (→ Cancelled legale),
     * gated su cancel. Raccoglie una motivazione facoltativa (comunicata al
     * richiedente) e delega a CancelAppointment.
     */
    public static function cancelAction(): Action
    {
        return Action::make('cancel')
            ->label(__('appointments.shelter.cancel'))
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->modalHeading(__('appointments.shelter.cancel_heading'))
            ->modalSubmitActionLabel(__('appointments.shelter.cancel_submit'))
            ->authorize('cancel')
            ->visible(fn (Appointment $record): bool => $record->status->canTransitionTo(AppointmentStatus::Cancelled))
            ->schema([
                Textarea::make('reason')
                    ->label(__('appointments.shelter.cancel_reason'))
                    ->helperText(__('appointments.shelter.cancel_reason_help'))
                    ->rows(3)
                    ->maxLength(2000),
            ])
            ->action(function (Appointment $record, array $data): void {
                $reason = ($data['reason'] ?? null) !== null && $data['reason'] !== ''
                    ? (string) $data['reason']
                    : null;

                try {
                    app(CancelAppointment::class)->handle($record, self::actor(), $reason);
                } catch (InvalidAppointmentTransition $exception) {
                    self::notifyFailure(__('appointments.shelter.cancel_failed'), $exception->getMessage());

                    return;
                }

                self::notifySuccess(__('appointments.shelter.cancel_done'));
            });
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

    private static function notifySuccess(string $title): void
    {
        Notification::make()
            ->title($title)
            ->success()
            ->send();
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
