<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Actions\Auth\AuthenticateUser;
use App\Data\Auth\LoginData;
use App\Livewire\Auth\Concerns\RendersAuthPage;
use App\Support\Seo\PublicPageSeo;
use Illuminate\Contracts\Session\Session;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * `/accedi` (route `login`): accesso dell'adottante (e di chiunque abbia un account) dal
 * sito pubblico. Componente sottile: valida con {@see LoginData} e delega ad
 * {@see AuthenticateUser} (rate limit, sessione, destinazione). Solo per gli ospiti
 * (middleware `guest` sulla pagina, controllo nell'Action sulle richieste Livewire).
 * Dopo un errore la password viene svuotata: non torna al browser nello snapshot.
 */
class LoginPage extends Component
{
    use RendersAuthPage;

    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public function authenticate(AuthenticateUser $authenticate, Request $request, Session $session): void
    {
        $this->submissions++;

        try {
            $destination = $authenticate->handle(LoginData::validateAndCreate([
                'email' => $this->email,
                'password' => $this->password,
                'remember' => $this->remember,
            ]), (string) $request->ip(), $session);
        } catch (ValidationException $exception) {
            $this->reset('password');

            throw $exception;
        }

        // Ricarica completa: header, menu account e token della nuova sessione.
        $this->redirect($destination);
    }

    public function render(PublicPageSeo $seo): View
    {
        return $this->authView($seo, 'livewire.auth.login', 'login', route('login'));
    }
}
