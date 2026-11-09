<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Http\Middleware\SetLocale;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Session\Session;

/**
 * Chiude la sessione dell'utente del sito pubblico (guard `web`, lo stesso dei pannelli).
 *
 * Esce dal guard (anche il cookie "ricordami"), invalida la sessione (nuovo id, dati
 * cancellati: nessun residuo dell'account resta leggibile) e rigenera il token CSRF.
 * L'unica cosa conservata è la lingua scelta col selettore ({@see SetLocale}): è una
 * preferenza del browser, non un dato dell'account.
 *
 * Nessun effetto sui ruoli: a differenza del logout del pannello adottante, non passa
 * da EnsureAdopterRole. Per un ospite è un no-op (a parte la nuova sessione).
 */
final readonly class LogoutUser
{
    public function __construct(private AuthFactory $auth) {}

    public function handle(Session $session): void
    {
        $guard = $this->auth->guard('web');

        if ($guard instanceof StatefulGuard) {
            $guard->logout();
        }

        $locale = $session->get(SetLocale::SESSION_KEY);

        $session->invalidate();
        $session->regenerateToken();

        if (is_string($locale)) {
            $session->put(SetLocale::SESSION_KEY, $locale);
        }
    }
}
