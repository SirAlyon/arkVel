<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Actions\Auth\ResetPassword;
use App\Data\Auth\ResetPasswordData;
use App\Livewire\Auth\Concerns\RendersAuthPage;
use App\Support\Auth\PasswordPolicy;
use App\Support\Seo\PublicPageSeo;
use Illuminate\Contracts\Session\Session;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * `/reimposta-password/{token}?email=…` (route `password.reset`): nuova password dal link
 * della mail. Componente sottile: valida con {@see ResetPasswordData} e delega a
 * {@see ResetPassword}; poi la pagina di accesso con un toast di conferma. Il token
 * viene dall'URL ed è bloccato lato client. Solo per gli ospiti. Dopo un errore le
 * password vengono svuotate: non tornano al browser nello snapshot.
 */
class ResetPasswordPage extends Component
{
    use RendersAuthPage;

    #[Locked]
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(Request $request, string $token): void
    {
        $this->token = $token;
        $this->email = is_string($email = $request->query('email')) ? $email : '';
    }

    public function resetPassword(ResetPassword $resetPassword, Request $request, Session $session): void
    {
        $this->submissions++;

        try {
            $resetPassword->handle(ResetPasswordData::validateAndCreate([
                'token' => $this->token,
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
            ]), (string) $request->ip(), $session);
        } catch (ValidationException $exception) {
            $this->reset('password', 'password_confirmation');

            throw $exception;
        }

        $session->flash('toast', ['type' => 'success', 'title' => __('auth_public.reset.done')]);

        $this->redirectRoute('login');
    }

    public function render(PublicPageSeo $seo): View
    {
        // Canonical senza il token (è un segreto): la pagina di richiesta del link.
        return $this->authView($seo, 'livewire.auth.reset-password', 'reset', route('password.request'))
            ->with('passwordMinLength', PasswordPolicy::minLength());
    }
}
