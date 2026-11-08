<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Data\Auth\ResetPasswordData;
use App\Exceptions\AlreadyAuthenticated;
use App\Models\User;
use App\Support\Auth\AuthRateLimiter;
use App\Support\Auth\GuestOnly;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\PasswordBroker;
use Illuminate\Contracts\Auth\PasswordBrokerFactory;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Nuova password dal link ricevuto via email (/reimposta-password/{token}).
 *
 * Replica il reset del pannello Filament che sostituisce: il password broker verifica
 * token ed email; la password viene salvata con l'hash, il remember token ruota (i
 * cookie "ricordami" già emessi smettono di valere) ed è emesso l'evento PasswordReset.
 * Come prima, l'utente NON viene autenticato: torna alla pagina di accesso. La sessione
 * corrente viene comunque rigenerata (nuovo id).
 *
 * Token scaduto o non valido ed email sconosciuta danno lo stesso messaggio, sul modulo
 * (chiave `form`, non sul campo email): il modulo non rivela quali indirizzi sono
 * registrati, e la pagina propone di chiedere un nuovo link.
 *
 * Solo ospiti ({@see GuestOnly}): il middleware `guest` non vale sulle richieste Livewire.
 *
 * Rate limit: MAX_ATTEMPTS tentativi per coppia email + IP in DECAY_SECONDS.
 */
final readonly class ResetPassword
{
    public const MAX_ATTEMPTS = 5;

    public const DECAY_SECONDS = 600;

    public function __construct(
        private PasswordBrokerFactory $passwords,
        private GuestOnly $guestOnly,
        private AuthRateLimiter $rateLimiter,
        private Hasher $hasher,
        private Dispatcher $events,
    ) {}

    /**
     * @throws AlreadyAuthenticated la sessione è già autenticata
     * @throws ValidationException link non valido/scaduto o troppi tentativi (campo `form`)
     */
    public function handle(ResetPasswordData $data, string $ip, Session $session): void
    {
        $this->guestOnly->ensure();

        $key = AuthRateLimiter::key('password-reset', mb_strtolower($data->email), $ip);

        $this->rateLimiter->ensureAvailable($key, self::MAX_ATTEMPTS, 'form');
        $this->rateLimiter->hit($key, self::DECAY_SECONDS);

        $status = $this->passwords->broker()->reset(
            ['email' => $data->email, 'password' => $data->password, 'token' => $data->token],
            function (User $user, #[\SensitiveParameter] string $password): void {
                $user->forceFill([
                    'password' => $this->hasher->make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                $this->events->dispatch(new PasswordReset($user));
            },
        );

        if ($status !== PasswordBroker::PASSWORD_RESET) {
            throw ValidationException::withMessages(['form' => __('auth_public.reset.invalid_link')]);
        }

        $this->rateLimiter->clear($key);
        $session->regenerate();
    }
}
