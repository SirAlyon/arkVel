<?php

declare(strict_types=1);

namespace App\Filament\Adopter\Pages;

use App\Actions\Fostering\WithdrawFosterApplication;
use App\Enums\FosterApplicationStatus;
use App\Models\FosterApplication;
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
 * Pagina "I miei affidi" del pannello adottante.
 *
 * Specchio esatto di {@see MyApplications}: elenco SELF-OWNED delle richieste di
 * affido dell'utente autenticato. Non c'è tenancy: l'isolamento è per ownership,
 * imposto sia dallo scope della query (`where('user_id', ...)`) sia dalla
 * FosterApplicationPolicy (view/withdraw sulla singola riga).
 *
 * Pagina sottile: la query filtra per user_id e l'unica azione (ritiro) delega
 * all'Action {@see WithdrawFosterApplication}. Nessuna business logic qui: lo
 * stato pre/post-terminale e la guardia di ownership vivono nel dominio. Quando
 * la richiesta ha un affido fisico associato (placement) mostriamo tipo e date
 * dell'affido, in sola lettura.
 */
class MyFosterApplications extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;

    protected string $view = 'filament.adopter.pages.my-foster-applications';

    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string
    {
        return __('fostering.my.title');
    }

    public function getTitle(): string
    {
        return __('fostering.my.title');
    }

    /**
     * Richieste di affido dell'utente corrente, dalle più recenti. Eager loading
     * di animale (+media), ente e affido fisico per evitare N+1 nei badge,
     * thumbnail e colonne di placement. Lo scoping per ownership è nella
     * `where('user_id', ...)`: nessuna fuga cross-utente.
     */
    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = Auth::user();

        return $table
            ->query(
                FosterApplication::query()
                    ->where('user_id', $user->getKey())
                    ->with(['animal.media', 'organization', 'placement'])
                    ->latest('submitted_at'),
            )
            ->columns([
                ImageColumn::make('animal_thumb')
                    ->label(__('fostering.my.animal'))
                    ->state(fn (FosterApplication $record): string => $record->animal->getFirstMediaUrl('photos', 'thumb')
                        ?: $record->animal->getFirstMediaUrl('avatar', 'thumb'))
                    ->circular(),
                TextColumn::make('animal.name')
                    ->label(__('fostering.my.animal'))
                    ->weight('bold')
                    ->url(fn (FosterApplication $record): string => route('animals.show', $record->animal))
                    ->openUrlInNewTab()
                    ->searchable(),
                TextColumn::make('organization.name')
                    ->label(__('fostering.my.organization'))
                    ->toggleable(),
                TextColumn::make('status')
                    ->label(__('fostering.my.status'))
                    ->badge()
                    ->formatStateUsing(fn (FosterApplication $record): string => $record->statusEnum()->getLabel())
                    ->color(fn (FosterApplication $record): string => $record->statusEnum()->getColor()),
                TextColumn::make('submitted_at')
                    ->label(__('fostering.my.submitted_at'))
                    ->dateTime('d/m/Y')
                    ->sortable(),
                // Colonne dell'affido fisico (presenti solo quando esiste un
                // placement): tipo, inizio e fine, in sola lettura.
                TextColumn::make('placement.type')
                    ->label(__('fostering.my.placement_type'))
                    ->badge()
                    ->formatStateUsing(fn (FosterApplication $record): ?string => $record->placement?->type->getLabel())
                    ->color(fn (FosterApplication $record): ?string => $record->placement?->type->getColor())
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('placement.start_date')
                    ->label(__('fostering.my.placement_start'))
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('placement.end_date')
                    ->label(__('fostering.my.placement_end'))
                    ->date('d/m/Y')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('decision_reason')
                    ->label(__('fostering.my.decision_reason'))
                    ->placeholder('—')
                    ->wrap()
                    ->toggleable(),
            ])
            ->recordActions([
                Action::make('withdraw')
                    ->label(__('fostering.my.withdraw'))
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('fostering.my.withdraw_confirm_heading'))
                    ->modalDescription(__('fostering.my.withdraw_confirm_body'))
                    ->modalSubmitActionLabel(__('fostering.my.withdraw_confirm_submit'))
                    // Visibile solo in stato pre-terminale E autorizzato dalla policy
                    // (proprietario). I terminali (Completed/Rejected/Withdrawn) sono
                    // esclusi: il grafo non consente il ritiro.
                    ->visible(fn (FosterApplication $record): bool => $this->canWithdraw($record))
                    ->action(function (FosterApplication $record, WithdrawFosterApplication $withdrawFosterApplication): void {
                        /** @var User $user */
                        $user = Auth::user();

                        // Difesa in profondità: la policy è già valutata da visible(),
                        // ma la ri-verifichiamo prima di mutare (la UI non è il confine).
                        $this->authorize('withdraw', $record);

                        $withdrawFosterApplication->handle($record, $user);

                        Notification::make()
                            ->success()
                            ->title(__('fostering.my.withdraw_done'))
                            ->send();
                    }),
            ])
            ->emptyStateHeading(__('fostering.my.empty_title'))
            ->emptyStateDescription(__('fostering.my.empty_body'))
            ->emptyStateIcon(Heroicon::OutlinedHomeModern);
    }

    /**
     * L'azione di ritiro è disponibile solo se l'utente è il proprietario (policy)
     * e lo stato è pre-terminale. I terminali sono Completed/Rejected/Withdrawn;
     * Active resta ritirabile (il ritiro chiude l'affido e riporta l'animale
     * disponibile, gestito nell'Action).
     */
    private function canWithdraw(FosterApplication $record): bool
    {
        $terminal = [
            FosterApplicationStatus::Completed,
            FosterApplicationStatus::Rejected,
            FosterApplicationStatus::Withdrawn,
        ];

        return Auth::user()?->can('withdraw', $record) === true
            && ! in_array($record->statusEnum(), $terminal, strict: true);
    }
}
