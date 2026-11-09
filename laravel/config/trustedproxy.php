<?php

declare(strict_types=1);

/*
 | Proxy fidati (letti dal middleware TrustProxies del framework, sempre attivo).
 |
 | Dietro un bilanciatore o un reverse proxy l'IP del client arriva in X-Forwarded-For:
 | senza dichiarare il proxy, $request->ip() è l'IP del proxy e i rate limit per IP
 | dell'autenticazione pubblica (App\Actions\Auth) varrebbero per tutti i visitatori insieme.
 |
 | TRUSTED_PROXIES: IP o CIDR separati da virgola (es. 10.0.0.0/8,172.16.0.0/12), oppure
 | `*` per fidarsi del chiamante diretto (solo se l'app NON è raggiungibile se non
 | attraverso il proxy). Vuoto: nessun proxy, si usa l'IP della connessione.
 */

return [

    'proxies' => env('TRUSTED_PROXIES') ?: null,

];
