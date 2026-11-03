<?php

declare(strict_types=1);

namespace App\Filament\Adopter\Pages;

use App\Actions\Applications\WithdrawApplication;
use App\Enums\ApplicationStatus;
use App\Models\AdoptionApplication;
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
 * Pagina "Le mie candidature" del pannello adottante.
 *
 * Elenco SELF-OWNED: mostra ESCLUSIVAMENTE le candidature dell'utente
 * autenticato ({@see User::applications()}). Non c'è tenancy: l'isolamento è per
 * ownership, imposto sia dallo scope della query sia dalla
 * AdoptionApplicationPolicy (view/withdraw sulla singola riga).
 *
 * Pagina sottile: la query filtra per user_id e l'unica azione (ritiro) delega
 * all'Action {@see WithdrawApplication}. Nessuna business logic qui: lo stato
 * pre/post-terminale e la guardia di ownership vivono nel dominio.
 */
class MyApplications extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected string $view = 'filament.adopter.pages.my-applications';

    public static function getNavigationLabel(): string
    {
        return __('applications.my.title');
    }

    public function getTitle(): string
    {
        return __('applications.my.title');
    }

    /**
     * Candidature dell'utente corrente, dalle più recenti. Eager loading di
     * animale (+media) ed ente per evitare N+1 nei badge/thumbnail. Lo scoping per
     * ownership è nella `where('user_id', ...)`: nessuna fuga cross-utente.
     */
    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = Auth::user();

        return $table
            ->query(
                AdoptionApplication::query()
                    ->where('user_id', $user->getKey())
                    ->with(['animal.media', 'organization'])
                    ->latest('submitted_at'),
            )
            ->columns([
                ImageColumn::make('animal_thumb')
                    ->label(__('applications.my.animal'))
                    ->state(fn (AdoptionApplication $record): string => $record->animal->getFirstMediaUrl('photos', 'thumb')
                        ?: $record->animal->getFirstMediaUrl('avatar', 'thumb'))
                    ->circular(),
                TextColumn::make('animal.name')
                    ->label(__('applications.my.animal'))
                    ->weight('bold')
                    ->url(fn (AdoptionApplication $record): string => route('animals.show', $record->animal))
                    ->openUrlInNewTab()
                    ->searchable(),
                TextColumn::make('organization.name')
                    ->label(__('applications.my.organization'))
                    ->toggleable(),
                TextColumn::make('status')
                    ->label(__('applications.my.status'))
                    ->badge()
                    ->formatStateUsing(fn (AdoptionApplication $record): string => $record->statusEnum()->getLabel())
                    ->color(fn (AdoptionApplication $record): string => $record->statusEnum()->getColor()),
                TextColumn::make('submitted_at')
                    ->label(__('applications.my.submitted_at'))
                    ->dateTime('d/m/Y')
                    ->sortable(),
                TextColumn::make('decision_reason')
                    ->label(__('applications.my.decision_reason'))
                    ->placeholder('—')
                    ->wrap()
                    ->toggleable(),
            ])
            ->recordActions([
                Action::make('withdraw')
                    ->label(__('applications.my.withdraw'))
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading(__('applications.my.withdraw_confirm_heading'))
                    ->modalDescription(__('applications.my.withdraw_confirm_body'))
                    ->modalSubmitActionLabel(__('applications.my.withdraw_confirm_submit'))
                    // Visibile solo in stato pre-terminale E autorizzato dalla policy
                    // (proprietario). I terminali (Completed/Rejected/Withdrawn) sono
                    // esclusi: il grafo non consente il ritiro.
                    ->visible(fn (AdoptionApplication $record): bool => $this->canWithdraw($record))
                    ->action(function (AdoptionApplication $record, WithdrawApplication $withdrawApplication): void {
                        /** @var User $user */
                        $user = Auth::user();

                        // Difesa in profondità: la policy è già valutata da visible(),
                        // ma la ri-verifichiamo prima di mutare (la UI non è il confine).
                        $this->authorize('withdraw', $record);

                        $withdrawApplication->handle($record, $user);

                        Notification::make()
                            ->success()
                            ->title(__('applications.my.withdraw_done'))
                            ->send();
                    }),
            ])
            ->emptyStateHeading(__('applications.my.empty_title'))
            ->emptyStateDescription(__('applications.my.empty_body'))
            ->emptyStateIcon(Heroicon::OutlinedDocumentText);
    }

    /**
     * L'azione di ritiro è disponibile solo se l'utente è il proprietario (policy)
     * e lo stato è pre-terminale. I terminali sono Completed/Rejected/Withdrawn.
     */
    private function canWithdraw(AdoptionApplication $record): bool
    {
        $terminal = [
            ApplicationStatus::Completed,
            ApplicationStatus::Rejected,
            ApplicationStatus::Withdrawn,
        ];

        return Auth::user()?->can('withdraw', $record) === true
            && ! in_array($record->statusEnum(), $terminal, strict: true);
    }
}
