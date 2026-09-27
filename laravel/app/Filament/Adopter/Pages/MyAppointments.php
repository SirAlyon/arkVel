<?php

declare(strict_types=1);

namespace App\Filament\Adopter\Pages;

use App\Actions\Appointments\CancelAppointment;
use App\Enums\AppointmentStatus;
use App\Exceptions\InvalidAppointmentTransition;
use App\Models\Appointment;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

/**
 * Pagina "I miei appuntamenti" del pannello adottante.
 *
 * Specchio di {@see MyFosterApplications}: elenco SELF-OWNED degli appuntamenti
 * dell'utente autenticato. Non c'è tenancy: l'isolamento è per ownership, imposto
 * sia dallo scope della query (`where('user_id', ...)`) sia dalla AppointmentPolicy
 * (cancel sulla singola riga).
 *
 * Pagina sottile: la query filtra per user_id e l'unica azione (annullamento)
 * delega all'Action {@see CancelAppointment}. Nessuna business logic qui: la
 * guardia di transizione e la notifica alla controparte vivono nel dominio.
 * L'annullamento è nascosto sugli stati terminali (Completed/Cancelled/NoShow),
 * dove il grafo non consente l'uscita.
 */
class MyAppointments extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected string $view = 'filament.adopter.pages.my-appointments';

    protected static ?int $navigationSort = 30;

    public static function getNavigationLabel(): string
    {
        return __('appointments.my.title');
    }

    public function getTitle(): string
    {
        return __('appointments.my.title');
    }

    /**
     * Appuntamenti dell'utente corrente, dai più recenti per orario. Eager loading
     * di animale (+media) ed ente per evitare N+1 nella thumbnail e nei badge. Lo
     * scoping per ownership è nella `where('user_id', ...)`: nessuna fuga
     * cross-utente.
     */
    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = Auth::user();

        return $table
            ->query(
                Appointment::query()
                    ->where('user_id', $user->getKey())
                    ->with(['animal.media', 'organization'])
                    ->latest('scheduled_at'),
            )
            ->columns([
                ImageColumn::make('animal_thumb')
                    ->label(__('appointments.my.animal'))
                    ->state(fn (Appointment $record): string => $record->animal->getFirstMediaUrl('photos', 'thumb')
                        ?: $record->animal->getFirstMediaUrl('avatar', 'thumb'))
                    ->circular(),
                TextColumn::make('animal.name')
                    ->label(__('appointments.my.animal'))
                    ->weight('bold')
                    ->url(fn (Appointment $record): string => route('animals.show', $record->animal))
                    ->openUrlInNewTab()
                    ->searchable(),
                TextColumn::make('organization.name')
                    ->label(__('appointments.my.organization'))
                    ->toggleable(),
                TextColumn::make('type')
                    ->label(__('appointments.my.type'))
                    ->badge()
                    ->formatStateUsing(fn (Appointment $record): string => $record->type->getLabel())
                    ->color(fn (Appointment $record): string => $record->type->getColor()),
                TextColumn::make('status')
                    ->label(__('appointments.my.status'))
                    ->badge()
                    ->formatStateUsing(fn (Appointment $record): string => $record->status->getLabel())
                    ->color(fn (Appointment $record): string => $record->status->getColor()),
                TextColumn::make('scheduled_at')
                    ->label(__('appointments.my.scheduled_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('cancel')
                    ->label(__('appointments.my.cancel'))
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('appointments.my.cancel_confirm_heading'))
                    ->modalDescription(__('appointments.my.cancel_confirm_body'))
                    ->modalSubmitActionLabel(__('appointments.my.cancel_confirm_submit'))
                    // Visibile solo in stato pre-terminale E autorizzato dalla policy
                    // (proprietario). I terminali (Completed/Cancelled/NoShow) sono
                    // esclusi: il grafo non consente l'annullamento.
                    ->visible(fn (Appointment $record): bool => $this->canCancel($record))
                    ->action(function (Appointment $record, CancelAppointment $cancelAppointment): void {
                        /** @var User $user */
                        $user = Auth::user();

                        // Difesa in profondità: la policy è già valutata da visible(),
                        // ma la ri-verifichiamo prima di mutare (la UI non è il confine).
                        $this->authorize('cancel', $record);

                        try {
                            $cancelAppointment->handle($record, $user);
                        } catch (InvalidAppointmentTransition $exception) {
                            // Difesa: offriamo l'azione solo su stati pre-terminali, ma
                            // se lo stato è cambiato nel frattempo notifichiamo senza
                            // propagare (nessun crash, stato invariato).
                            Notification::make()
                                ->danger()
                                ->title(__('appointments.my.cancel_failed'))
                                ->body($exception->getMessage())
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->success()
                            ->title(__('appointments.my.cancel_done'))
                            ->send();
                    }),
            ])
            ->emptyStateHeading(__('appointments.my.empty_title'))
            ->emptyStateDescription(__('appointments.my.empty_body'))
            ->emptyStateIcon(Heroicon::OutlinedCalendarDays);
    }

    /**
     * L'annullamento è disponibile solo se l'utente è il proprietario (policy) e lo
     * stato è pre-terminale. I terminali sono Completed/Cancelled/NoShow; Requested
     * e Confirmed restano annullabili (il grafo lo consente).
     */
    private function canCancel(Appointment $record): bool
    {
        $terminal = [
            AppointmentStatus::Completed,
            AppointmentStatus::Cancelled,
            AppointmentStatus::NoShow,
        ];

        return Auth::user()?->can('cancel', $record) === true
            && ! in_array($record->status, $terminal, strict: true);
    }
}
