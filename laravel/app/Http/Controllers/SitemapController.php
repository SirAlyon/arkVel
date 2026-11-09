<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Seo\CanonicalUrl;
use App\Support\Seo\Sitemap\PublicSitemap;
use App\Support\Seo\Sitemap\SitemapXml;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\Response;

/**
 * `GET /sitemap.xml`: le pagine pubbliche indicizzabili ({@see PublicSitemap}).
 *
 * Cache breve ({@see self::TTL_SECONDS}): i crawler la chiedono spesso e la query
 * scorre tutto il catalogo. Al peggio un animale appena adottato resta elencato per
 * qualche minuto e la sua pagina risponde 404, cosa che i motori gestiscono.
 *
 * La cache è una sola per tutti e NON dipende dalla richiesta: gli URL sono costruiti da
 * APP_URL ({@see CanonicalUrl}), quindi una richiesta con un header Host
 * arbitrario non può avvelenarla.
 *
 * Senza lo stack `web` (routes/web.php): niente sessione, cookie, CSRF né lingua, la
 * risposta è identica per tutti e non apre una sessione a ogni passaggio di un crawler.
 */
class SitemapController extends Controller
{
    public const string CACHE_KEY = 'seo.sitemap.xml';

    public const int TTL_SECONDS = 600;

    public function __invoke(Cache $cache, PublicSitemap $sitemap, SitemapXml $xml): Response
    {
        $content = $cache->remember(
            self::CACHE_KEY,
            self::TTL_SECONDS,
            static fn (): string => $xml->render($sitemap->urls()),
        );

        return response((string) $content, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }
}
