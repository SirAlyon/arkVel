<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Seo\RobotsTxt;
use Illuminate\Http\Response;

/**
 * `GET /robots.txt`, generato da {@see RobotsTxt} per poter indicare l'URL assoluto
 * della sitemap. Sostituisce il vecchio file statico public/robots.txt: un file in
 * public/ verrebbe servito dal web server prima di arrivare a Laravel.
 *
 * Senza lo stack `web` (routes/web.php): niente sessione, cookie, CSRF né lingua.
 */
class RobotsTxtController extends Controller
{
    public function __invoke(RobotsTxt $robots): Response
    {
        return response($robots->render(), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
