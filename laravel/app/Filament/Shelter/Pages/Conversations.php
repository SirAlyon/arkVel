<?php

declare(strict_types=1);

namespace App\Filament\Shelter\Pages;

use App\Actions\Messaging\MarkThreadRead;
use App\Actions\Messaging\PostMessage;
use App\Actions\Messaging\StartMessageThread;
use App\Enums\PermissionName;
use App\Filament\Messaging\Support\ThreadPresenter;
use App\Models\AdoptionApplication;
use App\Models\Appointment;
use App\Models\FosterApplication;
use App\Models\MessageThread;
use App\Models\Organization;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
 * Pagina "Conversazioni" del pannello ente (shelter), TENANT-SCOPED.
 *
 * Specchio di {@see AppointmentCalendar} per la messaggistica: elenco delle
 * conversazioni del tenant corrente, con apertura in modale (storico + risposta) e
 * avvio di una nuova conversazione verso un adottante GIÀ in relazione con l'ente.
 *
 * ISOLAMENTO CROSS-TENANT: la query di lista e la risoluzione del record sono
 * vincolate esplicitamente a organization_id = tenant corrente (`forOrganization`
 * / {@see resolveTenantThread()} con findOrFail → 404 per i thread di altri enti).
 * Non ci affidiamo a un global scope: il vincolo è diretto e testato. La
 * MessageThreadPolicy (view/reply) rafforza il confine sulla singola riga.
 *
 * Pagina SOTTILE: interroga (tenant-scoped) e delega.
 *  - all'apertura del modale la conversazione è marcata letta per il lato ENTE →
 *    {@see MarkThreadRead};
 *  - la risposta → {@see PostMessage}, gated dalla Policy `reply` (MessageManage +
 *    membership verificata): il volontario (solo MessageView) non la vede;
 *  - "Nuova conversazione" → {@see StartMessageThread}, con l'adottante ristretto a
 *    chi è GIÀ in relazione con l'ente (GDPR: mai la lista globale degli utenti).
 */
class Conversations extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected string $view = 'filament.shelter.pages.conversations';

    protected static ?int $navigationSort = 45;

    public static function getNavigationGroup(): ?string
    {
        return __('messaging.shelter.nav_group');
    }

    public static function getNavigationLabel(): string
    {
        return __('messaging.shelter.nav_label');
    }

    public function getTitle(): string
    {
        return __('messaging.shelter.title');
    }

    /**
     * Accesso: mirror del gating delle Resource — l'utente deve poter vedere i
     * messaggi (MessageThreadPolicy::viewAny + MessageView) ed essere un membro
     * VERIFICATO del tenant corrente. Lo scoping dei DATI è comunque garantito dalla
     * query tenant-scoped; qui filtriamo solo l'accesso alla pagina.
     */
    public static function canAccess(): bool
    {
        $user = Auth::user();
        $tenant = Filament::getTenant();

        if (! $user instanceof User || ! $tenant instanceof Organization) {
            return false;
        }

        return $user->can(PermissionName::MessageView->value)
            && $user->isVerifiedMemberOf($tenant);
    }

    /**
     * Badge di navigazione: conteggio delle conversazioni del tenant con messaggi
     * non letti per il lato ente. Best-effort (nessuna query se il tenant manca).
     */
    public static function getNavigationBadge(): ?string
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Organization) {
            return null;
        }

        // Conteggio DB-side (EXISTS correlato): quanti thread hanno messaggi non letti
        // lato ente. Niente idratazione di tutti i thread+messaggi a ogni render.
        $unread = MessageThread::query()
            ->forOrganization($tenant)
            ->unreadForOrg()
            ->count();

        return $unread > 0 ? (string) $unread : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    /**
     * Conversazioni del tenant corrente, dalle più recenti per ultimo messaggio.
     * ISOLAMENTO: `forOrganization($tenant)` vincola a organization_id = tenant.
     * Eager loading di adottante, animale e messaggi (col mittente) per i badge, le
     * colonne di contesto e il modale dello storico.
     */
    public function table(Table $table): Table
    {
        $tenant = Filament::getTenant();

        $query = MessageThread::query()
            ->with(['user', 'animal', 'messages.user'])
            ->latest('last_message_at');

        if ($tenant instanceof Organization) {
            $query->forOrganization($tenant);
        } else {
            // Senza tenant (non dovrebbe accadere: pagina gated) nessuna riga.
            $query->whereRaw('1 = 0');
        }

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('user.name')
                    ->label(__('messaging.thread.adopter'))
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
                    // Il badge mostra il conteggio dei non letti lato ente; vuoto a
                    // zero (nessun badge). Il record può essere null in fase di
                    // rendering dell'header/empty-state: trattiamolo come "nessuno".
                    ->state(fn (?MessageThread $record): ?string => $record !== null && $record->unreadCountForOrg() > 0
                        ? (string) $record->unreadCountForOrg()
                        : null),
                TextColumn::make('last_message_at')
                    ->label(__('messaging.thread.last_message_at'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                $this->openAction(),
            ])
            ->emptyStateHeading(__('messaging.shelter.empty_title'))
            ->emptyStateDescription(__('messaging.shelter.empty_body'))
            ->emptyStateIcon(Heroicon::OutlinedChatBubbleLeftRight);
    }

    /**
     * Azioni di intestazione: avvio di una nuova conversazione. Visibile SOLO a chi
     * può gestire (MessageManage): il volontario (sola vista) non la vede.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            $this->newThreadAction(),
        ];
    }

    /**
     * Azione "Apri": storico (partial condiviso) nel contenuto del modale + box di
     * risposta come form. Risolve il record TENANT-SCOPED (404 cross-tenant). Alla
     * mount marca letto il lato ENTE (MarkThreadRead). L'invio delega a PostMessage,
     * gated dalla Policy `reply` (MessageManage + membership verificata): il
     * volontario può aprire/leggere ma il submit è negato lato server.
     */
    private function openAction(): Action
    {
        return Action::make('open')
            ->label(__('messaging.thread.open'))
            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->modalHeading(__('messaging.thread.open_heading'))
            ->modalSubmitActionLabel(__('messaging.reply.send'))
            ->modalCancelActionLabel(__('messaging.thread.close'))
            ->authorize(fn (MessageThread $record): bool => Auth::user()?->can('view', $record) === true)
            ->mountUsing(function (MessageThread $record, MarkThreadRead $markThreadRead): void {
                /** @var User $user */
                $user = Auth::user();
                $markThreadRead->handle($record, $user);
            })
            ->modalContent(fn (MessageThread $record): View => ViewFacade::make('filament.messaging.conversation', [
                'thread' => $record->load('messages.user'),
                'viewerIsAdopter' => false,
            ]))
            ->schema([
                Textarea::make('body')
                    ->label(__('messaging.reply.label'))
                    ->placeholder(__('messaging.reply.placeholder'))
                    ->required()
                    ->rows(3)
                    ->maxLength(5000)
                    // Il box di risposta è disabilitato per chi non può rispondere
                    // (volontario): il submit resta comunque negato lato server
                    // dalla ri-verifica della Policy `reply` nell'action().
                    ->disabled(fn (?MessageThread $record): bool => $record === null || Auth::user()?->can('reply', $record) !== true),
            ])
            ->action(function (MessageThread $record, array $data, PostMessage $postMessage): void {
                /** @var User $user */
                $user = Auth::user();

                // POSTARE richiede `reply` (MessageManage + membership verificata):
                // la ri-verifichiamo prima di mutare (la UI non è il confine).
                $this->authorize('reply', $record);

                $postMessage->handle($record, $user, (string) $data['body']);

                Notification::make()
                    ->success()
                    ->title(__('messaging.reply.sent'))
                    ->send();
            });
    }

    /**
     * Azione "Nuova conversazione": lo staff scrive a un adottante. L'adottante è
     * ristretto a chi è GIÀ in relazione con il tenant (candidatura/affido/
     * appuntamento/conversazione esistente): la lista globale degli utenti NON è mai
     * esposta (GDPR). Autorizzata su MessageManage; delega a StartMessageThread.
     */
    private function newThreadAction(): Action
    {
        return Action::make('newThread')
            ->label(__('messaging.shelter.new'))
            ->icon(Heroicon::OutlinedPencilSquare)
            ->modalHeading(__('messaging.shelter.new_heading'))
            ->modalSubmitActionLabel(__('messaging.shelter.new_submit'))
            ->authorize(fn (): bool => $this->canManageMessages())
            ->schema([
                Select::make('user_id')
                    ->label(__('messaging.shelter.new_adopter'))
                    ->helperText(__('messaging.shelter.new_adopter_help'))
                    ->options(fn (): array => $this->relatedAdopterOptions())
                    ->searchable()
                    ->native(false)
                    ->required(),
                TextInput::make('subject')
                    ->label(__('messaging.shelter.new_subject'))
                    ->maxLength(255),
                Textarea::make('body')
                    ->label(__('messaging.shelter.new_body'))
                    ->required()
                    ->rows(4)
                    ->maxLength(5000),
            ])
            ->action(function (array $data): void {
                $this->startThread($data);
            });
    }

    /**
     * Avvia la conversazione delegando a StartMessageThread, dopo aver ri-validato
     * che l'adottante appartenga all'insieme consentito (backstop oltre la UI): un id
     * fuori scope viene rifiutato senza toccare il DB (GDPR). L'autore è lo staff
     * corrente, l'ente è il tenant, l'adottante è il destinatario scelto.
     *
     * @param  array<string, mixed>  $data
     */
    private function startThread(array $data): void
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Organization || ! $this->canManageMessages()) {
            return;
        }

        $adopterId = (int) ($data['user_id'] ?? 0);

        // Backstop: solo adottanti già in relazione con l'ente.
        if (! array_key_exists($adopterId, $this->relatedAdopterOptions())) {
            Notification::make()
                ->title(__('messaging.shelter.new_failed'))
                ->danger()
                ->send();

            return;
        }

        /** @var User $author */
        $author = Auth::user();
        $adopter = User::query()->findOrFail($adopterId);

        $subject = ($data['subject'] ?? null) !== null && $data['subject'] !== ''
            ? (string) $data['subject']
            : null;

        app(StartMessageThread::class)->handle(
            author: $author,
            organization: $tenant,
            adopter: $adopter,
            body: (string) $data['body'],
            subject: $subject,
        );

        Notification::make()
            ->title(__('messaging.shelter.new_done'))
            ->success()
            ->send();
    }

    /**
     * L'utente corrente può GESTIRE i messaggi del tenant (rispondere / avviare)?
     * Rispecchia MessageThreadPolicy::reply (MessageManage + membership verificata):
     * il volontario (solo MessageView) è escluso. Gate di UI, ricontrollato dalla
     * Policy su ogni azione server.
     */
    public function canManageMessages(): bool
    {
        $user = Auth::user();
        $tenant = Filament::getTenant();

        if (! $user instanceof User || ! $tenant instanceof Organization) {
            return false;
        }

        return $user->can(PermissionName::MessageManage->value)
            && $user->isVerifiedMemberOf($tenant);
    }

    /**
     * Adottanti selezionabili per una nuova conversazione: SOLO utenti GIÀ in
     * relazione con il tenant corrente — hanno una candidatura di adozione, una
     * richiesta di affido, un appuntamento o una conversazione per questo ente. La
     * lista globale degli utenti NON è mai esposta (GDPR: minimizzazione).
     *
     * @return array<int, string>
     */
    private function relatedAdopterOptions(): array
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Organization) {
            return [];
        }

        $organizationId = $tenant->getKey();

        // Union degli user_id già in relazione con l'ente. Query dirette per
        // user_id/organization_id: un utente estraneo non compare nella lista.
        $relatedUserIds = AdoptionApplication::query()
            ->where('organization_id', $organizationId)
            ->distinct()
            ->pluck('user_id')
            ->merge(
                FosterApplication::query()
                    ->where('organization_id', $organizationId)
                    ->distinct()
                    ->pluck('user_id'),
            )
            ->merge(
                Appointment::query()
                    ->where('organization_id', $organizationId)
                    ->distinct()
                    ->pluck('user_id'),
            )
            ->merge(
                MessageThread::query()
                    ->where('organization_id', $organizationId)
                    ->distinct()
                    ->pluck('user_id'),
            )
            ->unique()
            ->all();

        return User::query()
            ->whereIn('id', $relatedUserIds)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * Risolve il thread vincolandolo al tenant corrente: un id di un altro ente (o
     * inesistente) solleva 404 (findOrFail sulla query tenant-scoped). Stessa
     * garanzia del record binding tenant-scoped delle Resource. Attualmente usato
     * come backstop testabile dell'isolamento cross-tenant.
     */
    public function resolveTenantThread(int $id): MessageThread
    {
        $tenant = Filament::getTenant();

        abort_unless($tenant instanceof Organization, 404);

        return MessageThread::query()
            ->forOrganization($tenant)
            ->findOrFail($id);
    }
}
