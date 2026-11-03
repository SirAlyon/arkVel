<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Strumenti di sviluppo (es. /_styleguide): esistono solo in ambiente `local`.
 * Altrove rispondono 404, indistinguibili da una rotta inesistente.
 */
final class EnsureLocalEnvironment
{
    public function __construct(private readonly Application $app) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($this->app->isLocal(), 404);

        return $next($request);
    }
}
