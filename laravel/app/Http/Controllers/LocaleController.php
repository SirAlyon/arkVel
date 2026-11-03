<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AppLocale;
use App\Http\Middleware\SetLocale;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Salva in sessione la lingua scelta dal visitatore e torna alla pagina di provenienza.
 * La lingua è validata dal binding dell'enum: un codice non supportato risponde 404.
 */
class LocaleController extends Controller
{
    public function __invoke(Request $request, AppLocale $locale): RedirectResponse
    {
        $request->session()->put(SetLocale::SESSION_KEY, $locale->value);

        return redirect()->back(fallback: route('home'));
    }
}
