<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Data\Auth\LoginData;
use App\Exceptions\AlreadyAuthenticated;
use App\Support\Auth\AuthRateLimiter;
use App\Support\Auth\GuestOnly;
use App\Support\Auth\IntendedUrl;
use App\Support\Auth\PostLoginDestination;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Session\Session;
use Illuminate\Validation\ValidationException;

/**
 * Accesso dal sito pubblico (/accedi) sul guard `web`, lo stesso dei pannelli.
 *
 * Replica il login del pannello adottante che sostituisce:
 * - credenziali verificate dal guard (eventi Attempting/Failed/Login/Authenticated di
 *   Laravel, confronto della password a tempo costante con il Timebox del SessionGuard);
 * - "ricordami" con il cookie remember del guard;
 * - sessione rigenerata dopo l'accesso (niente session fixation);
 * - destinazione: l'URL intended se interno ({@see IntendedUrl}), altrimenti quella
 *   dell'utente ({@see PostLoginDestination}): /account per gli adottanti, /manage per lo
 *   staff degli enti, /admin per il Super Admin. Lo staff non passa da /account, dove
 *   EnsureAdopterRole gli darebbe il ruolo Adopter.
 *
 * Solo ospiti ({@see GuestOnly}): il middleware `guest` non vale sulle richieste Livewire.
 *
 * Due limiti, controllati prima di verificare le credenziali:
 * - MAX_ATTEMPTS tentativi FALLITI per coppia email + IP in DECAY_SECONDS (forza bruta su
 *   un account; un accesso riuscito azzera il contatore);
 * - MAX_ATTEMPTS_PER_IP tentativi in tutto (riusciti o no, qualunque email) per IP in
 *   IP_DECAY_SECONDS (password spraying: poche password provate su molti account).
 * Al limite un messaggio con l'attesa. Il messaggio d'errore delle credenziali è unico
 * (email inesistente o password sbagliata non si distinguono).
 */
final readonly class AuthenticateUser
{
    public const MAX_ATTEMPTS = 5;

    public const DECAY_SECONDS = 60;

    public const MAX_ATTEMPTS_PER_IP = 30;

    public const IP_DECAY_SECONDS = 60;

    public function __construct(
        private AuthFactory $auth,
        private GuestOnly $guestOnly,
        private AuthRateLimiter $rateLimiter,
        private IntendedUrl $intendedUrl,
        private PostLoginDestination $destination,
    ) {}

    /**
     * @return string URL a cui mandare l'utente dopo l'accesso
     *
     * @throws AlreadyAuthenticated la sessione è già autenticata
     * @throws ValidationException credenziali errate o troppi tentativi (campo `email`)
     */
    public function handle(LoginData $data, string $ip, Session $session): string
    {
        $this->guestOnly->ensure();

        $ipKey = AuthRateLimiter::key('login-ip', $ip);
        $key = AuthRateLimiter::key('login', mb_strtolower($data->email), $ip);

        $this->rateLimiter->ensureAvailable($ipKey, self::MAX_ATTEMPTS_PER_IP, 'email');
        $this->rateLimiter->ensureAvailable($key, self::MAX_ATTEMPTS, 'email');
        $this->rateLimiter->hit($ipKey, self::IP_DECAY_SECONDS);

        $credentials = ['email' => $data->email, 'password' => $data->password];

        if (! $this->guard()->attempt($credentials, $data->remember)) {
            $this->rateLimiter->hit($key, self::DECAY_SECONDS);

            throw ValidationException::withMessages(['email' => __('auth_public.login.failed')]);
        }

        $this->rateLimiter->clear($key);
        $session->regenerate();

        return $this->intendedUrl->pull($session, $this->destination->for($this->guard()->user()));
    }

    /** Il guard `web` è un SessionGuard (config/auth.php): sempre stateful. */
    private function guard(): StatefulGuard
    {
        /** @var StatefulGuard $guard */
        $guard = $this->auth->guard('web');

        return $guard;
    }
}
