<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Livewire\Auth\Concerns\RendersAuthPage;
use App\Models\User;
use App\Support\Auth\IntendedUrl;
use App\Support\Auth\PostLoginDestination;
use App\Support\Seo\PublicPageSeo;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Session\Session;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * `/verifica-email` (route `verification.notice`): l'utente autenticato con l'email da
 * verificare (ci arriva dopo la registrazione e dal middleware `verified` del pannello
 * /account). Spiega dove è partita la mail e offre il reinvio (modulo POST verso
 * `verification.send`, senza JavaScript) e l'uscita. Chi ha già verificato prosegue
 * verso l'intended interno o la sua destinazione ({@see PostLoginDestination}), come la
 * prompt del pannello Filament.
 */
class VerifyEmailPage extends Component
{
    use RendersAuthPage;

    public function mount(Guard $guard, Session $session, IntendedUrl $intendedUrl, PostLoginDestination $destination): void
    {
        $user = $this->user($guard);

        if ($user->hasVerifiedEmail()) {
            $this->redirect($intendedUrl->pull($session, $destination->for($user)));
        }
    }

    public function render(Guard $guard, PublicPageSeo $seo): View
    {
        return $this->authView($seo, 'livewire.auth.verify-email', 'verify', route('verification.notice'))
            ->with('email', $this->user($guard)->getEmailForVerification());
    }

    /** La route è dietro `auth`: l'utente c'è sempre. */
    private function user(Guard $guard): User
    {
        /** @var User $user */
        $user = $guard->user();

        return $user;
    }
}
