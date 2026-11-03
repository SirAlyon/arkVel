<?php

declare(strict_types=1);

namespace App\Filament\Adopter\Pages;

use App\Actions\Messaging\MarkThreadRead;
use App\Actions\Messaging\PostMessage;
use App\Filament\Messaging\Support\ThreadPresenter;
use App\Models\MessageThread;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View as ViewFacade;

/**
 * Pagina "Messaggi" del pannello adottante.
 *
 * Specchio di {@see MyAppointments}: elenco SELF-OWNED delle conversazioni
 * dell'utente autenticato. Non c'è tenancy: l'isolamento è per OWNERSHIP, imposto
 * sia dallo scope della query (`forAdopter`, cioè `where('user_id', ...)`) sia
 * dalla MessageThreadPolicy (view/reply sulla singola riga).
 *
 * Pagina SOTTILE: la query filtra per l'utente corrente; l'azione "open" apre un
 * modale di sola lettura con lo storico (partial condiviso) e un box di risposta.
 * Nessuna business logic qui:
 *  - all'apertura del modale la conversazione è marcata letta per il lato
 *    adottante → delega a {@see MarkThreadRead};
 *  - l'invio della risposta → delega a {@see PostMessage} (gated dalla Policy
 *    `reply`: solo il proprietario del thread). La notifica alla controparte e
 *    l'aggiornamento dei marcatori vivono nell'Action.
 */
class MyMessages extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected string $view = 'filament.adopter.pages.my-messages';

    protected static ?int $navigationSort = 40;

    public static function getNavigationLabel(): string
    {
        return __('messaging.adopter.nav_label');
    }

    public function getTitle(): string
    {
        return __('messaging.adopter.title');
    }

    /**
     * Conversazioni dell'utente corrente, dalle più recenti per ultimo messaggio.
     * Eager loading di ente, animale e messaggi (col mittente) per evitare N+1 nei
     * badge, nelle colonne di contesto e nel modale dello storico. Lo scoping per
     * ownership è nello scope `forAdopter`: nessuna fuga cross-utente.
     */
    public function table(Table $table): Table
    {
        /** @var User $user */
        $user = Auth::user();

        return $table
            ->query(
                MessageThread::query()
                    ->forAdopter($user)
                    ->with(['organization', 'animal', 'messages.user'])
                    ->latest('last_message_at'),
            )
            ->columns([
                TextColumn::make('organization.name')
                    ->label(__('messaging.thread.organization'))
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('subject')
                    ->label(__('messaging.thread.subject'))
                    ->formatStateUsing(fn (MessageThread $record): string => ThreadPresenter::subject($record))
                    ->wrap(),
                TextColumn::make('last_message')
                    ->label(__('messaging.thread.last_message'))
                    ->state(fn (MessageThread $record): string => ThreadPresenter::excerpt($record))
                    ->wrap()
                    ->toggleable(),
                TextColumn::make('unread')
                    ->label(__('messaging.thread.unread'))
                    ->badge()
                    ->color('danger')
                    // Il badge mostra il conteggio dei non letti lato adottante; vuoto
                    // a zero (nessun badge). Il record può essere null in fase di
                    // rendering dell'header/empty-state: trattiamolo come "nessuno".
                    ->state(fn (?MessageThread $record): ?string => $record !== null && $record->unreadCountForAdopter() > 0
                        ? (string) $record->unreadCountForAdopter()
                        : null),
                TextColumn::make('last_message_at')
                    ->label(__('messaging.thread.last_message_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                $this->openAction(),
            ])
            ->emptyStateHeading(__('messaging.adopter.empty_title'))
            ->emptyStateDescription(__('messaging.adopter.empty_body'))
            ->emptyStateIcon(Heroicon::OutlinedChatBubbleLeftRight);
    }

    /**
     * Azione "Apri": mostra lo storico (partial condiviso) nel contenuto del modale
     * e offre un box di risposta come form del modale. All'apertura la conversazione
     * è marcata letta per il lato adottante (MarkThreadRead); il badge dei non letti
     * si azzera al refresh. L'invio delega a PostMessage, gated dalla Policy `reply`.
     */
    private function openAction(): Action
    {
        return Action::make('open')
            ->label(__('messaging.thread.open'))
            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->modalHeading(__('messaging.thread.open_heading'))
            ->modalSubmitActionLabel(__('messaging.reply.send'))
            ->modalCancelActionLabel(__('messaging.thread.close'))
            // Difesa in profondità: solo il proprietario del thread (Policy view).
            ->authorize(fn (MessageThread $record): bool => Auth::user()?->can('view', $record) === true)
            // All'apertura: marca letto il lato adottante (lettura silenziosa).
            ->mountUsing(function (MessageThread $record, MarkThreadRead $markThreadRead): void {
                /** @var User $user */
                $user = Auth::user();
                $markThreadRead->handle($record, $user);
            })
            ->modalContent(fn (MessageThread $record): View => ViewFacade::make('filament.messaging.conversation', [
                'thread' => $record->load('messages.user'),
                'viewerIsAdopter' => true,
            ]))
            ->schema([
                Textarea::make('body')
                    ->label(__('messaging.reply.label'))
                    ->placeholder(__('messaging.reply.placeholder'))
                    ->required()
                    ->rows(3)
                    ->maxLength(5000),
            ])
            ->action(function (MessageThread $record, array $data, PostMessage $postMessage): void {
                /** @var User $user */
                $user = Auth::user();

                // La UI è autorizzata (view), ma POSTARE richiede `reply`: la
                // ri-verifichiamo prima di mutare (la UI non è il confine).
                $this->authorize('reply', $record);

                $postMessage->handle($record, $user, (string) $data['body']);

                Notification::make()
                    ->success()
                    ->title(__('messaging.reply.sent'))
                    ->send();
            });
    }
}
