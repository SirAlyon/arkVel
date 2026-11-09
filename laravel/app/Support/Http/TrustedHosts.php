<?php

declare(strict_types=1);

namespace App\Support\Http;

/**
 * Host accettati dall'applicazione (middleware TrustHosts, bootstrap/app.php).
 *
 * Perché: route()/url() costruiscono gli URL assoluti dall'header Host della richiesta.
 * Senza una lista di host fidati, una richiesta con `Host: evil.example` produrrebbe
 * canonical, og:url, JSON-LD, link delle email (reset password dei pannelli Filament) e
 * URL firmati che puntano a evil.example: è la base del "host header poisoning" e
 * dell'avvelenamento delle cache condivise. Con la lista, Symfony rifiuta l'host non
 * fidato (SuspiciousOperationException, che Laravel risponde con un 400) prima che
 * qualunque URL venga generato.
 *
 * Host fidati: quello di APP_URL (esatto, senza sottodomini: i pannelli vivono su path,
 * non su domini) più quelli elencati in APP_TRUSTED_HOSTS (`config('app.trusted_hosts')`,
 * separati da virgola: es. il dominio `www.` o l'host usato dagli health check del
 * bilanciatore, che altrimenti riceverebbero 400).
 *
 * Il middleware del framework non applica la lista in `local` e nei test (anche quelli
 * browser, serviti su 127.0.0.1:porta): lì l'host non è un confine di sicurezza.
 */
final class TrustedHosts
{
    /**
     * Pattern (regex di Symfony, senza delimitatori) degli host fidati.
     *
     * @param  array<array-key, mixed>  $extraHosts
     * @return list<string>
     */
    public static function patterns(string $appUrl, array $extraHosts = []): array
    {
        $hosts = [parse_url($appUrl, PHP_URL_HOST)];

        foreach ($extraHosts as $host) {
            $hosts[] = is_string($host) ? trim($host) : null;
        }

        $patterns = [];

        foreach ($hosts as $host) {
            if (is_string($host) && $host !== '') {
                $patterns[] = '^'.preg_quote(mb_strtolower($host)).'$';
            }
        }

        return array_values(array_unique($patterns));
    }
}
