<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\AppLocale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Imposta la lingua dell'interfaccia del sito pubblico.
 *
 * Priorità: scelta esplicita salvata in sessione (selettore lingua) → header
 * Accept-Language del browser → lingua di default dell'app. Solo lingue di
 * {@see AppLocale}: un valore sconosciuto in sessione viene ignorato.
 *
 * Registrato sul gruppo `web` (sito pubblico + richieste Livewire). I pannelli
 * Filament hanno uno stack di middleware proprio e non ne sono toccati.
 */
class SetLocale
{
    public const SESSION_KEY = 'locale';

    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale($this->resolve($request));

        return $next($request);
    }

    private function resolve(Request $request): string
    {
        $chosen = $request->hasSession() ? $request->session()->get(self::SESSION_KEY) : null;

        if (is_string($chosen) && AppLocale::tryFrom($chosen) !== null) {
            return $chosen;
        }

        // Lingue del browser in ordine di preferenza (es. ['en_GB', 'en']): vince la
        // prima la cui lingua primaria è supportata. Nessuna corrispondenza → default.
        foreach ($request->getLanguages() as $language) {
            $supported = AppLocale::tryFrom(strtolower(strtok($language, '_') ?: $language));

            if ($supported !== null) {
                return $supported->value;
            }
        }

        return (string) config('app.locale');
    }
}
