<?php

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
        // Le rotte web protette da `auth` (es. il download .ics di un appuntamento)
        // vivono FUORI dai pannelli Filament, che hanno un proprio login. Non esiste
        // una rotta `login` generica: senza questo redirect il middleware `auth`
        // fallirebbe cercando route('login'). L'account holder (proprietario di un
        // appuntamento) accede dal pannello adottante, quindi mandiamo lì gli ospiti.
        $middleware->redirectGuestsTo(fn (): string => route('filament.adopter.auth.login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
