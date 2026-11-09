<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Filament\Http\Middleware\Authenticate;
use Illuminate\Http\Request;

/**
 * Autenticazione del pannello adottante (/account): il middleware di Filament
 * (stesso controllo canAccessPanel, stesso guard), ma gli ospiti vanno alla pagina di
 * accesso PUBBLICA (route `login`, /accedi): dalla fase UI-5 il pannello non ha più un
 * login proprio (AdopterPanelProvider). L'URL richiesto resta in sessione come intended.
 */
class AuthenticateAdopter extends Authenticate
{
    /**
     * @param  Request  $request
     */
    protected function redirectTo($request): string
    {
        return route('login');
    }
}
