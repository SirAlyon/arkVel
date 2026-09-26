<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Http\Middleware\EnsureAdopterRole;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Pannello degli adottanti su /account.
 *
 * NON multi-tenant: l'adottante è una persona fisica, non un membro d'ente.
 * L'isolamento dei dati NON passa dalla tenancy (assente qui) ma da:
 *  - OWNERSHIP: l'AdopterProfilePolicy autorizza solo il proprietario del
 *    profilo (confronto `is()` sull'utente), sia per view sia per update. Un
 *    adottante non può vedere né modificare il profilo altrui.
 *  - VERIFICA EMAIL: emailVerification() rende obbligatoria la verifica su
 *    questo pannello (middleware `verified` sulle rotte protette).
 * Non è il pannello di default (resta l'admin).
 *
 * Autenticazione (Fase 2, slice 2):
 * - login()/registration(): accesso e self-registration dell'account adottante.
 * - passwordReset(): richiesta e reset password via email.
 * - emailVerification(): la verifica email è OBBLIGATORIA su questo pannello.
 * - profile(): pagina account (nome/email/password) per l'utente autenticato.
 *
 * Ruolo Adopter: assegnato TEAM-INDEPENDENT dal middleware EnsureAdopterRole a
 * ogni accesso autenticato (idempotente). È bookkeeping/segmentazione: il ruolo
 * non porta permessi, l'autorizzazione è per ownership (vedi sopra).
 */
class AdopterPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('adopter')
            ->path('account')
            ->login()
            ->registration()
            ->passwordReset()
            ->emailVerification()
            ->profile()
            ->colors([
                'primary' => Color::Rose,
            ])
            ->discoverResources(in: app_path('Filament/Adopter/Resources'), for: 'App\Filament\Adopter\Resources')
            ->discoverPages(in: app_path('Filament/Adopter/Pages'), for: 'App\Filament\Adopter\Pages')
            ->discoverWidgets(in: app_path('Filament/Adopter/Widgets'), for: 'App\Filament\Adopter\Widgets')
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
                // Assegna il ruolo Adopter (team-independent) agli utenti
                // autenticati: gira DOPO Authenticate, così l'utente esiste.
                EnsureAdopterRole::class,
            ]);
    }
}
