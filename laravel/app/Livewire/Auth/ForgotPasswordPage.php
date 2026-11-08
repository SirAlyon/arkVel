<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Actions\Auth\SendPasswordResetLink;
use App\Data\Auth\ForgotPasswordData;
use App\Livewire\Auth\Concerns\RendersAuthPage;
use App\Support\Auth\PasswordPolicy;
use App\Support\Seo\PublicPageSeo;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Livewire\Component;

/**
 * `/password-dimenticata` (route `password.request`): richiesta del link per reimpostare
 * la password. Componente sottile: valida con {@see ForgotPasswordData} e delega a
 * {@see SendPasswordResetLink}. L'esito mostrato è sempre lo stesso, che l'email sia
 * registrata o no: un blocco di conferma che prende il focus (lo screen reader lo legge per
 * intero). Solo per gli ospiti.
 */
class ForgotPasswordPage extends Component
{
    use RendersAuthPage;

    public string $email = '';

    /** Il link è stato richiesto: la pagina mostra la conferma (uguale per ogni email). */
    public bool $sent = false;

    public function sendLink(SendPasswordResetLink $send, Request $request): void
    {
        $this->submissions++;

        $send->handle(ForgotPasswordData::validateAndCreate(['email' => $this->email]), (string) $request->ip());

        $this->sent = true;
    }

    public function render(PublicPageSeo $seo): View
    {
        return $this->authView($seo, 'livewire.auth.forgot-password', 'forgot', route('password.request'))
            ->with('linkExpiryMinutes', PasswordPolicy::resetLinkExpiryMinutes());
    }
}
