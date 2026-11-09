<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header di lingua sulle pagine HTML: la stessa URL serve italiano o inglese secondo la
 * sessione o l'Accept-Language (SetLocale), quindi una cache intermedia deve distinguere
 * le varianti (`Vary: Accept-Language`) e la risposta dichiara la lingua servita
 * (`Content-Language`). Le risposte non HTML (sitemap, robots, JSON, file) non cambiano.
 */
final class ContentLanguage
{
    public function __construct(private readonly Application $app) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! str_starts_with((string) $response->headers->get('Content-Type'), 'text/html')) {
            return $response;
        }

        $response->headers->set('Content-Language', str_replace('_', '-', $this->app->getLocale()));
        $response->setVary('Accept-Language', replace: false);

        return $response;
    }
}
