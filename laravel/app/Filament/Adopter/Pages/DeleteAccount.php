<?php

declare(strict_types=1);

namespace App\Filament\Adopter\Pages;

use App\Actions\Account\AnonymizeUserAccount;
use App\Actions\Auth\LogoutUser;
use App\Exceptions\CannotAnonymizeSoleOwner;
use App\Models\User;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Auth;

/**
 * "Danger zone" del pannello adottante: eliminazione self-service dell'account
 * (diritto all'oblio, GDPR art. 17).
 *
 * Pagina SOTTILE: spiega le conseguenze e offre un'unica azione distruttiva che
 * richiede conferma esplicita (digitare la propria email) e delega all'Action di
 * dominio {@see AnonymizeUserAccount}. Nessuna business logic qui.
 *
 * SELF-SERVICE: opera SEMPRE e solo su `Auth::user()` — nessuna azione
 * cross-utente da autorizzare con una Policy. Dopo l'anonimizzazione l'utente
 * viene disconnesso (sessione invalidata) e rediretto alla home pubblica con un
 * flash localizzato. Il caso "unico titolare di un ente" è intercettato dalla
 * guardia dell'Action e mostrato come messaggio chiaro, senza anonimizzare.
 */
class DeleteAccount extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrash;

    protected string $view = 'filament.adopter.pages.delete-account';

    protected static ?int $navigationSort = 90;

    public static function getNavigationLabel(): string
    {
        return __('account.delete.nav');
    }

    public function getTitle(): string
    {
        return __('account.delete.title');
    }

    /**
     * Azione distruttiva: conferma esplicita (email) → delega all'Action → logout
     * + redirect alla home. Renderizzata nella view della pagina come
     * `{{ $this->deleteAccountAction }}`.
     */
    public function deleteAccountAction(): Action
    {
        return Action::make('deleteAccount')
            ->label(__('account.delete.button'))
            ->color('danger')
            ->icon(Heroicon::OutlinedTrash)
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedExclamationTriangle)
            ->modalHeading(__('account.delete.confirm_heading'))
            ->modalDescription(__('account.delete.confirm_body'))
            ->modalSubmitActionLabel(__('account.delete.confirm_submit'))
            ->schema([
                TextInput::make('confirmation_email')
                    ->label(__('account.delete.confirm_email_label'))
                    ->helperText(__('account.delete.confirm_email_help'))
                    ->required()
                    ->rule(static function (): Closure {
                        return static function (string $attribute, mixed $value, Closure $fail): void {
                            /** @var User|null $user */
                            $user = Auth::user();
                            $email = $user?->email;

                            if (! is_string($value) || $email === null || mb_strtolower(trim($value)) !== $email) {
                                $fail(__('account.delete.confirm_email_mismatch'));
                            }
                        };
                    }),
            ])
            ->action(function (AnonymizeUserAccount $anonymizeUserAccount, LogoutUser $logoutUser) {
                /** @var User $user */
                $user = Auth::user();

                try {
                    $anonymizeUserAccount->handle($user);
                } catch (CannotAnonymizeSoleOwner) {
                    Notification::make()
                        ->danger()
                        ->title(__('account.delete.sole_owner_title'))
                        ->body(__('account.delete.sole_owner_body'))
                        ->persistent()
                        ->send();

                    return;
                }

                // Chiude la sessione (invalida + rigenera token): l'account anonimo
                // non deve più poter operare nemmeno nella richiesta corrente. Si
                // risolve lo store dal container (`session.store`): è sempre
                // disponibile, anche nel contesto Livewire dove la richiesta non
                // porta la sessione agganciata.
                /** @var Session $session */
                $session = app('session.store');

                $logoutUser->handle($session);

                $session->flash('status', __('account.delete.done_flash'));

                $this->redirect(route('home'));
            });
    }
}
