<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Actions\Auth\RegisterAdopter;
use App\Livewire\Auth\Concerns\RendersAuthPage;
use App\Support\Auth\Honeypot;
use App\Support\Auth\PasswordPolicy;
use App\Support\Seo\PublicPageSeo;
use App\Support\Site\LegalLinks;
use Illuminate\Contracts\Session\Session;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * `/registrati` (route `register`): registrazione dell'adottante. Componente sottile: passa
 * l'input del modulo e il momento della sua comparsa (`renderedAt`, bloccato lato client)
 * a {@see RegisterAdopter}, che applica rate limit, trappola anti-bot ({@see Honeypot}),
 * validazione e creazione. Poi la pagina di verifica dell'email. Solo per gli ospiti.
 * Dopo un errore le password vengono svuotate: non tornano al browser nello snapshot.
 */
class RegisterPage extends Component
{
    use RendersAuthPage;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $privacy = false;

    /** Campo esca del honeypot (Honeypot::FIELD): resta vuoto per le persone. */
    public string $website = '';

    /** Momento (timestamp Unix) in cui il modulo è comparso: non modificabile dal client. */
    #[Locked]
    public int $renderedAt = 0;

    public function mount(): void
    {
        $this->renderedAt = Date::now()->getTimestamp();
    }

    public function register(RegisterAdopter $register, Request $request, Session $session): void
    {
        $this->submissions++;

        try {
            $register->handle([
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
                'privacy' => $this->privacy,
                Honeypot::FIELD => $this->website,
            ], $this->renderedAt, (string) $request->ip(), $session);
        } catch (ValidationException $exception) {
            $this->reset('password', 'password_confirmation');

            throw $exception;
        }

        $this->redirectRoute('verification.notice');
    }

    public function render(PublicPageSeo $seo, LegalLinks $legalLinks): View
    {
        return $this->authView($seo, 'livewire.auth.register', 'register', route('register'))
            ->with([
                'privacyPolicyUrl' => $legalLinks->privacyPolicy(),
                'passwordMinLength' => PasswordPolicy::minLength(),
            ]);
    }
}
