<?php

declare(strict_types=1);

namespace App\Filament\Adopter\Pages;

use App\Actions\Donations\CancelRecurringDonation;
use App\Enums\DonationType;
use App\Enums\RecurringDonationStatus;
use App\Models\Donation;
use App\Models\RecurringDonation;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Pagina "Le mie donazioni" del pannello adottante (P4-7).
 *
 * Specchio di {@see MyAppointments}: elenco SELF-OWNED delle donazioni ricorrenti
 * dell'utente autenticato + lo storico degli addebiti. Non c'è tenancy: l'isolamento è per
 * OWNERSHIP, imposto sia dallo scope della query (`where('user_id', ...)`) sia dalla
 * RecurringDonationPolicy (view/cancel sulla singola riga). Nessuna fuga cross-utente.
 *
 * Pagina SOTTILE: la tabella elenca le ricorrenti; l'unica azione (disdetta) delega
 * all'Action {@see CancelRecurringDonation} (idempotente, disdice su Stripe + stato
 * Canceled). Lo storico addebiti è una lista read-only dei {@see Donation} ricorrenti del
 * donatore. Nessuna business logic qui: la guardia e la chiamata a Stripe vivono nel dominio.
 */
class MyDonations extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHeart;

    protected string $view = 'filament.adopter.pages.my-donations';

    protected static ?int $navigationSort = 35;

    public static function getNavigationLabel(): string
    {
        return __('donations.my.title');
    }

    public function getTitle(): string
    {
        return __('donations.my.title');
    }

    /**
     * Donazioni ricorrenti dell'utente corrente, dalle più recenti. Eager loading
     * dell'ente per i badge. Lo scoping per ownership è nella `where('user_id', ...)`:
     * nessuna fuga cross-utente.
     */
    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = Auth::user();

        return $table
            ->query(
                RecurringDonation::query()
                    ->where('user_id', $user->getKey())
                    ->with('organization')
                    ->latest(),
            )
            ->columns([
                TextColumn::make('organization.name')
                    ->label(__('donations.my.organization'))
                    ->weight('bold')
                    ->url(fn (RecurringDonation $record): string => route('organizations.show', $record->organization->slug))
                    ->openUrlInNewTab()
                    ->searchable(),
                TextColumn::make('amount')
                    ->label(__('donations.my.amount'))
                    ->formatStateUsing(fn (RecurringDonation $record): string => __('donations.my.amount_monthly', [
                        'amount' => number_format($record->amountInEuros(), 2, ',', '.'),
                    ])),
                TextColumn::make('status')
                    ->label(__('donations.my.status'))
                    ->badge()
                    ->formatStateUsing(fn (RecurringDonation $record): string => $record->status->getLabel())
                    ->color(fn (RecurringDonation $record): string => $record->status->getColor()),
                TextColumn::make('started_at')
                    ->label(__('donations.my.started_at'))
                    ->dateTime('d/m/Y')
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('canceled_at')
                    ->label(__('donations.my.canceled_at'))
                    ->dateTime('d/m/Y')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->recordActions([
                Action::make('cancel')
                    ->label(__('donations.my.cancel'))
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('donations.my.cancel_confirm_heading'))
                    ->modalDescription(__('donations.my.cancel_confirm_body'))
                    ->modalSubmitActionLabel(__('donations.my.cancel_confirm_submit'))
                    // Visibile solo se non terminale (il grafo consente l'uscita) E
                    // autorizzato dalla policy (proprietario).
                    ->visible(fn (RecurringDonation $record): bool => $this->canCancel($record))
                    ->action(function (RecurringDonation $record, CancelRecurringDonation $cancelRecurringDonation): void {
                        /** @var User $user */
                        $user = Auth::user();

                        // Difesa in profondità: la policy è già valutata da visible(), ma
                        // la ri-verifichiamo prima di mutare (la UI non è il confine).
                        $this->authorize('cancel', $record);

                        $cancelRecurringDonation->handle($record, $user);

                        Notification::make()
                            ->success()
                            ->title(__('donations.my.cancel_done'))
                            ->send();
                    }),
            ])
            ->emptyStateHeading(__('donations.my.empty_title'))
            ->emptyStateDescription(__('donations.my.empty_body'))
            ->emptyStateIcon(Heroicon::OutlinedHeart);
    }

    /**
     * Storico degli addebiti ricorrenti del donatore corrente (read-only), dal più
     * recente. Scoping per ownership (`donor_user_id`) + tipo ricorrente: nessuna fuga
     * cross-utente, nessuna una tantum.
     *
     * @return Collection<int, Donation>
     */
    public function chargeHistory(): Collection
    {
        /** @var User $user */
        $user = Auth::user();

        return Donation::query()
            ->where('donor_user_id', $user->getKey())
            ->where('type', DonationType::Recurring)
            ->with('organization')
            ->latest('paid_at')
            ->get();
    }

    /**
     * La disdetta è disponibile solo se l'utente è il proprietario (policy) e lo stato non
     * è terminale (il grafo consente ancora l'uscita verso Canceled).
     */
    private function canCancel(RecurringDonation $record): bool
    {
        return Auth::user()?->can('cancel', $record) === true
            && $record->status->canTransitionTo(RecurringDonationStatus::Canceled);
    }
}
