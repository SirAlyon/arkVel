<?php

declare(strict_types=1);

namespace App\Support\Seo\Sitemap;

use Carbon\CarbonInterface;

/**
 * Una voce della sitemap: URL assoluto e, quando è affidabile, la data di ultima
 * modifica (`<lastmod>`). Le pagine di elenco non la dichiarano: Google usa lastmod
 * solo se è sempre accurato, e un elenco cambia anche quando un animale ne esce.
 */
final readonly class SitemapUrl
{
    public function __construct(
        public string $loc,
        public ?CarbonInterface $lastModified = null,
    ) {}
}
