<?php

declare(strict_types=1);

namespace App\Support\Seo;

use App\Support\Http\TrustedHosts;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Routing\UrlGenerator;

/**
 * URL assoluti sul dominio canonico (APP_URL), MAI sull'header Host della richiesta.
 *
 * Lo usano i documenti che si mettono in cache o che leggono i crawler fuori dalla
 * pagina (sitemap, robots.txt): la sitemap resta in cache 10 minuti per tutti, quindi
 * se i suoi URL seguissero l'host della richiesta, una sola richiesta con
 * `Host: evil.example` la avvelenerebbe. Difesa in profondità rispetto alla lista di
 * host fidati ({@see TrustedHosts}), che in local e nei test non è
 * applicata: questi URL sono corretti in ogni ambiente.
 *
 * Il path viene dalla route (relativo), il dominio e lo schema da APP_URL.
 */
final readonly class CanonicalUrl
{
    public function __construct(
        private UrlGenerator $url,
        private Config $config,
    ) {}

    /**
     * @param  array<array-key, mixed>|object|string|int  $parameters
     */
    public function route(string $name, array|object|string|int $parameters = []): string
    {
        $path = $this->url->route($name, $parameters, absolute: false);

        // Come l'URL generator: la home è la radice senza barra finale.
        return rtrim($this->root().'/'.ltrim($path, '/'), '/');
    }

    public function root(): string
    {
        return rtrim((string) $this->config->get('app.url'), '/');
    }
}
