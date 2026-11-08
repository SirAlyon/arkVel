<?php

declare(strict_types=1);

namespace App\Support\Seo\Sitemap;

use XMLWriter;

/**
 * Serializza le voci nel formato sitemaps.org 0.9. XMLWriter (estensione standard di
 * PHP, nessuna dipendenza) si occupa dell'escape di `&`, `<` e degli altri caratteri.
 */
final class SitemapXml
{
    public const string NAMESPACE = 'http://www.sitemaps.org/schemas/sitemap/0.9';

    /**
     * @param  iterable<SitemapUrl>  $urls
     */
    public function render(iterable $urls): string
    {
        $xml = new XMLWriter;
        $xml->openMemory();
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('urlset');
        $xml->writeAttribute('xmlns', self::NAMESPACE);

        foreach ($urls as $url) {
            $xml->startElement('url');
            $xml->writeElement('loc', $url->loc);

            if ($url->lastModified !== null) {
                $xml->writeElement('lastmod', $url->lastModified->toAtomString());
            }

            $xml->endElement();
        }

        $xml->endElement();
        $xml->endDocument();

        return $xml->outputMemory();
    }
}
