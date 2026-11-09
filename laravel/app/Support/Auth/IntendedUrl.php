<?php

declare(strict_types=1);

namespace App\Support\Auth;

use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Contracts\Session\Session;

/**
 * Destinazione dopo accesso o verifica email: l'URL "intended" salvato in sessione
 * (`url.intended`, scritto da `redirect()->guest()` del middleware `auth`/`verified` o
 * da una pagina che chiede il login, es. la scheda animale) oppure il default.
 *
 * Solo URL interni: un percorso relativo (`/animali/3`, mai `//host` né `/\host`) o un
 * URL assoluto con lo stesso schema, host e porta del sito. Qualunque altro valore
 * (un dominio esterno finito in sessione) viene scartato a favore del default: niente
 * open redirect, anche se un giorno l'intended arrivasse da un input dell'utente.
 */
final readonly class IntendedUrl
{
    public const SESSION_KEY = 'url.intended';

    public function __construct(private UrlGenerator $url) {}

    /** Legge e rimuove l'intended dalla sessione; il default se manca o non è interno. */
    public function pull(Session $session, string $default): string
    {
        $intended = $session->pull(self::SESSION_KEY);

        return is_string($intended) && $this->isInternal($intended) ? $intended : $default;
    }

    public function isInternal(string $url): bool
    {
        if ($url === '' || preg_match('/[\x00-\x1F\x7F\\\\]/', $url) === 1) {
            return false;
        }

        if (str_starts_with($url, '/')) {
            return ! str_starts_with($url, '//');
        }

        $target = parse_url($url);
        $site = parse_url($this->url->to('/'));

        if ($target === false || $site === false || ! isset($target['scheme'], $target['host'], $site['scheme'], $site['host'])) {
            return false;
        }

        return strtolower($target['scheme']) === strtolower($site['scheme'])
            && strtolower($target['host']) === strtolower($site['host'])
            && ($target['port'] ?? null) === ($site['port'] ?? null)
            && ! isset($target['user'])
            && ! isset($target['pass']);
    }
}
