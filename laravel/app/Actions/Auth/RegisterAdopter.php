<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Data\Auth\RegisterAdopterData;
use App\Exceptions\AlreadyAuthenticated;
use App\Models\User;
use App\Support\Auth\AuthRateLimiter;
use App\Support\Auth\GuestOnly;
use App\Support\Auth\Honeypot;
use App\Support\Site\LegalLinks;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registrazione dell'adottante dal sito pubblico (/registrati).
 *
 * Riceve l'input GREZZO del modulo e fa tutto lei, in quest'ordine:
 * 1. solo ospiti ({@see GuestOnly}): il middleware `guest` non vale sulle richieste Livewire;
 * 2. limite per IP su OGNI invio (MAX_SUBMISSIONS in SUBMISSIONS_DECAY_SECONDS), PRIMA
 *    della validazione: la regola "email già usata" risponde solo a un numero limitato di
 *    tentativi, quindi non si possono enumerare gli iscritti (OWASP: il messaggio esplicito
 *    è accettabile se limitato);
 * 3. trappola anti-bot ({@see Honeypot}: campo esca e tempo minimo), i cui rifiuti contano
 *    nel limite del punto 2;
 * 4. limite per IP sulle registrazioni RIUSCITE (MAX_REGISTRATIONS in
 *    REGISTRATIONS_DECAY_SECONDS): 10 all'ora, abbastanza per una famiglia, una scuola o
 *    un ufficio dietro lo stesso IP (NAT, reti condivise), non per creare account a raffica;
 * 5. validazione con {@see RegisterAdopterData} (email normalizzata in minuscolo).
 *
 * Poi replica la registrazione del pannello Filament adottante che sostituisce:
 * - crea SOLO l'utente (nome, email, password con hash): nessun profilo adottante, che
 *   resta il questionario compilato in /account (ManageAdopterProfile, con il suo
 *   consenso al trattamento dei dati);
 * - email da verificare: evento `Registered` di Laravel, il cui listener manda la mail
 *   di verifica (User::sendEmailVerificationNotification(), notifica in coda come quella
 *   di Filament, con il link alla route pubblica `verification.verify`);
 * - accesso immediato con sessione rigenerata;
 * - ruolo di piattaforma `Adopter` ({@see AssignAdopterRole}): prima lo assegnava
 *   EnsureAdopterRole al redirect immediato su /account dopo la registrazione; ora la
 *   registrazione porta alla pagina di verifica pubblica, quindi lo assegniamo qui.
 *
 * In più (UI-5): la presa visione dell'informativa privacy, obbligatoria, è registrata
 * nell'activitylog (PRIVACY_NOTICE_LOG, soggetto e autore l'utente stesso, con la versione
 * dell'informativa e la lingua, nessun altro dato personale). Non è un "consenso": il
 * trattamento per gestire l'account ha base contrattuale (GDPR art. 6.1.b).
 *
 * Due registrazioni concorrenti con la stessa email superano entrambe la regola `unique`:
 * la seconda trova il vincolo del database e riceve lo stesso errore di validazione.
 */
final readonly class RegisterAdopter
{
    /** Invii del modulo per IP (validi o no) prima di dover attendere. */
    public const MAX_SUBMISSIONS = 10;

    public const SUBMISSIONS_DECAY_SECONDS = 60;

    /** Registrazioni riuscite per IP. */
    public const MAX_REGISTRATIONS = 10;

    public const REGISTRATIONS_DECAY_SECONDS = 3600;

    public const PRIVACY_NOTICE_LOG = 'privacy_notice.acknowledged';

    public function __construct(
        private AuthFactory $auth,
        private GuestOnly $guestOnly,
        private AuthRateLimiter $rateLimiter,
        private Honeypot $honeypot,
        private AssignAdopterRole $assignAdopterRole,
        private LegalLinks $legalLinks,
        private Hasher $hasher,
        private Dispatcher $events,
    ) {}

    /**
     * @param  array<string, mixed>  $input  campi del modulo: name, email, password,
     *                                       password_confirmation, privacy e il campo esca
     *                                       Honeypot::FIELD
     * @param  int  $renderedAt  timestamp Unix della comparsa del modulo (lato server)
     *
     * @throws AlreadyAuthenticated la sessione è già autenticata
     * @throws ValidationException dati non validi, bot, troppi invii o registrazioni
     *                             (campo `form` per gli ultimi tre)
     */
    public function handle(array $input, int $renderedAt, string $ip, Session $session): User
    {
        $this->guestOnly->ensure();

        $submissions = AuthRateLimiter::key('register-submission', $ip);
        $registrations = AuthRateLimiter::key('register', $ip);

        $this->rateLimiter->ensureAvailable($submissions, self::MAX_SUBMISSIONS, 'form');
        $this->rateLimiter->hit($submissions, self::SUBMISSIONS_DECAY_SECONDS);

        $trap = $input[Honeypot::FIELD] ?? '';

        if ($this->honeypot->isBot(is_string($trap) ? $trap : 'filled', $renderedAt, Date::now()->getTimestamp())) {
            throw ValidationException::withMessages(['form' => __('auth_public.register.rejected')]);
        }

        $this->rateLimiter->ensureAvailable($registrations, self::MAX_REGISTRATIONS, 'form');

        $data = RegisterAdopterData::validateAndCreate($input);

        $user = $this->createUser($data);

        $this->rateLimiter->hit($registrations, self::REGISTRATIONS_DECAY_SECONDS);

        $this->events->dispatch(new Registered($user));

        /** @var StatefulGuard $guard il guard `web` è un SessionGuard */
        $guard = $this->auth->guard('web');
        $guard->login($user);

        $session->regenerate();

        return $user;
    }

    /**
     * @throws ValidationException l'email è stata registrata nel frattempo (campo `email`)
     */
    private function createUser(RegisterAdopterData $data): User
    {
        try {
            return DB::transaction(function () use ($data): User {
                $user = User::query()->create([
                    'name' => $data->name,
                    'email' => $data->email,
                    'password' => $this->hasher->make($data->password),
                ]);

                $this->assignAdopterRole->handle($user);

                activity()
                    ->performedOn($user)
                    ->causedBy($user)
                    ->withProperties([
                        'notice' => 'privacy_policy',
                        'policy_version' => $this->legalLinks->privacyPolicyVersion(),
                        'locale' => app()->getLocale(),
                    ])
                    ->log(self::PRIVACY_NOTICE_LOG);

                return $user;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['email' => __('auth_public.validation.email_taken')]);
        }
    }
}
