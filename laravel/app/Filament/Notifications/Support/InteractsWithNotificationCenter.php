<?php

declare(strict_types=1);

namespace App\Filament\Notifications\Support;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;

/**
 * Comportamento condiviso del CENTRO NOTIFICHE (storico completo delle notifiche
 * in-app), riusato identico dal pannello adottante e dal pannello ente. Le pagine
 * dei due pannelli restano sottili: dichiarano solo view e navigazione, tutto il
 * resto (tabella, azioni, badge) vive qui.
 *
 * ISOLAMENTO PER OWNERSHIP: la query interroga SEMPRE la relazione morph
 * `notifications()` dell'utente autenticato (vincolata a notifiable_type/_id
 * dell'utente da Laravel). Nessuna notifica di altri utenti può comparire. Le
 * azioni di lettura operano su righe appartenenti alla stessa relazione.
 *
 * Nessuna business logic di dominio: si leggono le notifiche dell'utente e si usa
 * il `markAsRead()` nativo di Laravel.
 *
 * @mixin Page
 * @mixin InteractsWithTable
 */
trait InteractsWithNotificationCenter
{
    public static function getNavigationLabel(): string
    {
        return __('notifications_center.nav_label');
    }

    public function getTitle(): string
    {
        return __('notifications_center.title');
    }

    /**
     * Badge di navigazione: conteggio delle notifiche NON lette dell'utente
     * corrente. Conteggio DB-side (where unreadNotifications), nessuna idratazione.
     */
    public static function getNavigationBadge(): ?string
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return null;
        }

        $unread = $user->unreadNotifications()->count();

        return $unread > 0 ? (string) $unread : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    /**
     * Storico delle notifiche dell'utente CORRENTE, dalle più recenti, paginato.
     * Lo scoping per ownership è nella relazione `notifications()` (morph vincolato
     * all'utente autenticato): nessuna fuga cross-utente.
     */
    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => $this->notificationsQuery())
            ->defaultSort('created_at', 'desc')
            ->columns([
                IconColumn::make('read_at')
                    ->label(__('notifications_center.column.state'))
                    // Pallino pieno = non letta (danger), check = letta (success).
                    ->icon(fn (DatabaseNotification $record): Heroicon => $record->read_at === null
                        ? Heroicon::ExclamationCircle
                        : Heroicon::CheckCircle)
                    ->color(fn (DatabaseNotification $record): string => $record->read_at === null
                        ? 'danger'
                        : 'success')
                    ->tooltip(fn (DatabaseNotification $record): string => $record->read_at === null
                        ? __('notifications_center.state.unread')
                        : __('notifications_center.state.read')),
                TextColumn::make('message')
                    ->label(__('notifications_center.column.message'))
                    ->state(fn (DatabaseNotification $record): string => $this->notificationMessage($record))
                    // Non lette in grassetto per distinguerle a colpo d'occhio.
                    ->weight(fn (DatabaseNotification $record): ?string => $record->read_at === null ? 'bold' : null)
                    ->wrap(),
                TextColumn::make('created_at')
                    ->label(__('notifications_center.column.received_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('unread')
                    ->label(__('notifications_center.filter.unread_only'))
                    ->placeholder(__('notifications_center.filter.unread_only'))
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNull('read_at'),
                        false: fn (Builder $query): Builder => $query->whereNotNull('read_at'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
            ])
            ->headerActions([
                $this->markAllReadAction(),
            ])
            ->recordActions([
                $this->openAction(),
                $this->markReadAction(),
            ])
            ->emptyStateHeading(__('notifications_center.empty_title'))
            ->emptyStateDescription(__('notifications_center.empty_body'))
            ->emptyStateIcon(Heroicon::OutlinedBell);
    }

    /**
     * Query base del centro notifiche: la relazione morph dell'utente corrente.
     *
     * @return Builder<DatabaseNotification>
     */
    protected function notificationsQuery(): Builder
    {
        /** @var User $user */
        $user = Auth::user();

        /** @var Builder<DatabaseNotification> $query */
        $query = $user->notifications()->getQuery();

        return $query;
    }

    /**
     * Messaggio leggibile della notifica: usa `data['message']` quando presente,
     * altrimenti un fallback tradotto. Il payload `data` è già un array (cast del
     * DatabaseNotification).
     */
    protected function notificationMessage(DatabaseNotification $record): string
    {
        // `data` è castato ad array dal DatabaseNotification.
        $message = $record->data['message'] ?? null;

        return is_string($message) && $message !== ''
            ? $message
            : __('notifications_center.fallback_message');
    }

    /**
     * URL d'azione della notifica, se presente nel payload (`data['url']`).
     */
    protected function notificationUrl(DatabaseNotification $record): ?string
    {
        // `data` è castato ad array dal DatabaseNotification.
        $url = $record->data['url'] ?? null;

        return is_string($url) && $url !== '' ? $url : null;
    }

    /**
     * "Apri": segna la notifica come letta e, se il payload porta un URL d'azione,
     * ci naviga. Se non c'è URL resta sulla pagina (no-op di navigazione) ma la
     * marca comunque letta.
     */
    protected function openAction(): Action
    {
        return Action::make('open')
            ->label(__('notifications_center.open'))
            ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
            ->url(fn (DatabaseNotification $record): ?string => $this->notificationUrl($record))
            ->openUrlInNewTab(false)
            ->visible(fn (DatabaseNotification $record): bool => $this->notificationUrl($record) !== null)
            ->action(function (DatabaseNotification $record): void {
                $record->markAsRead();
            });
    }

    /**
     * "Segna come letta": marca la singola notifica. Visibile solo se non letta.
     */
    protected function markReadAction(): Action
    {
        return Action::make('markRead')
            ->label(__('notifications_center.mark_read'))
            ->icon(Heroicon::OutlinedCheck)
            ->color('gray')
            ->visible(fn (DatabaseNotification $record): bool => $record->read_at === null)
            ->action(function (DatabaseNotification $record): void {
                $record->markAsRead();

                Notification::make()
                    ->success()
                    ->title(__('notifications_center.marked_read'))
                    ->send();
            });
    }

    /**
     * "Segna tutte come lette": marca tutte le non lette dell'utente corrente.
     * Usa `markAsRead()` nativo di Laravel sulla relazione scoped all'utente.
     */
    protected function markAllReadAction(): Action
    {
        return Action::make('markAllRead')
            ->label(__('notifications_center.mark_all_read'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('gray')
            ->visible(fn (): bool => Auth::user() instanceof User && Auth::user()->unreadNotifications()->exists())
            ->action(function (): void {
                /** @var User $user */
                $user = Auth::user();

                $user->unreadNotifications->markAsRead();

                Notification::make()
                    ->success()
                    ->title(__('notifications_center.all_marked_read'))
                    ->send();
            });
    }
}
