<?php

declare(strict_types=1);

namespace App\Support\Seo;

/**
 * Contenuto di robots.txt, generato per avere l'URL ASSOLUTO della sitemap (il
 * protocollo lo richiede e il dominio cambia per ambiente). L'URL è costruito da APP_URL
 * con {@see CanonicalUrl}, NON dall'header Host della richiesta: un Host arbitrario non
 * può far puntare la sitemap a un altro dominio.
 *
 * Bloccate le aree autenticate (pannelli Filament): /account (adottante), /manage
 * (enti), /admin. La styleguide non serve bloccarla: fuori da `local` risponde 404.
 * Il resto, asset e script di Livewire compresi, resta accessibile: i motori devono
 * poter rendere le pagine.
 */
final readonly class RobotsTxt
{
    /**
     * @var list<string>
     */
    public const array DISALLOWED = ['/account', '/manage', '/admin'];

    public function __construct(private CanonicalUrl $url) {}

    public function render(): string
    {
        $lines = ['User-agent: *'];

        foreach (self::DISALLOWED as $path) {
            $lines[] = 'Disallow: '.$path;
        }

        $lines[] = '';
        $lines[] = 'Sitemap: '.$this->url->route('seo.sitemap');

        return implode("\n", $lines)."\n";
    }
}
