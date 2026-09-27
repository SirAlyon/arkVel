<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Calendar\CalendarProviderRegistry;
use App\Support\Calendar\Providers\GoogleCalendarProvider;
use App\Support\Calendar\Providers\LogCalendarProvider;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Configura la sincronizzazione dei calendari esterni: registra il
 * {@see CalendarProviderRegistry} come singleton e vi aggiunge i provider disponibili.
 *
 * Punto di ESTENSIONE (Open/Closed / Dependency Inversion): la pipeline di push
 * dipende dal registry, non dai provider concreti. In Cal-4 è registrato SOLO il
 * {@see LogCalendarProvider} (fittizio, senza I/O), che rende la pipeline dimostrabile
 * e testabile end-to-end. I binding di Google (Cal-5) e CalDAV (Cal-6) si aggiungono
 * qui, senza toccare né il job né il registry.
 */
class CalendarSyncServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Singleton: un unico registro condiviso, popolato una volta al bootstrap.
        $this->app->singleton(CalendarProviderRegistry::class, function (Application $app): CalendarProviderRegistry {
            $registry = new CalendarProviderRegistry;

            // Provider demo/log: unico realmente utilizzabile senza credenziali (Cal-4).
            $registry->register($app->make(LogCalendarProvider::class));

            // Google Calendar (Cal-5): registrato SEMPRE, ma inerte finché non configurato
            // (i suoi metodi di rete sollevano CalendarProviderNotConfigured e la UI
            // disabilita la connessione). Così una connessione Google esistente è risolta
            // dal registry e il config-gating vive nel provider, non qui.
            $registry->register($app->make(GoogleCalendarProvider::class));

            // Cal-6: $registry->register($app->make(CalDavCalendarProvider::class));

            return $registry;
        });
    }
}
