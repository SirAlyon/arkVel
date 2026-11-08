<?php

use App\Http\Middleware\SetLocale;
use App\Support\Http\TrustedHosts;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Solo gli host fidati (APP_URL + APP_TRUSTED_HOSTS): gli URL assoluti generati da
        // route()/url() (canonical, Open Graph, JSON-LD, link delle email, URL firmati) non
        // possono seguire un header Host arbitrario. Non applicato in local e nei test.
        // Vedi App\Support\Http\TrustedHosts.
        $middleware->trustHosts(
            at: static fn (): array => TrustedHosts::patterns(
                (string) config('app.url'),
                (array) config('app.trusted_hosts', []),
            ),
            subdomains: false,
        );

        // Lingua dell'interfaccia del sito pubblico (sessione → Accept-Language → default).
        // Solo sul gruppo `web`: i pannelli Filament hanno uno stack proprio.
        $middleware->web(append: [SetLocale::class]);

        // Le rotte web protette da `auth` (es. il download .ics di un appuntamento)
        // vivono FUORI dai pannelli Filament, che hanno un proprio login. Non esiste
        // una rotta `login` generica: senza questo redirect il middleware `auth`
        // fallirebbe cercando route('login'). L'account holder (proprietario di un
        // appuntamento) accede dal pannello adottante, quindi mandiamo lì gli ospiti.
        $middleware->redirectGuestsTo(fn (): string => route('filament.adopter.auth.login'));

        // Il webhook Stripe (POST /stripe/webhook) è chiamato da Stripe, senza sessione
        // né token CSRF: l'autenticità è garantita dalla firma `Stripe-Signature`
        // verificata nel controller (P4-6). Lo escludiamo quindi dalla protezione CSRF.
        $middleware->validateCsrfTokens(except: [
            'stripe/webhook',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
