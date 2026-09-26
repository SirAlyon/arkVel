<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Filament\Shelter\Pages\Tenancy\RegisterOrganization;
use App\Http\Middleware\SetPermissionsTeamFromTenant;
use App\Models\Organization;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
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
 * Pannello degli enti (canili/rifugi/associazioni) su /manage.
 *
 * Multi-tenant: il tenant è l'Organization, risolto per slug. NON è il
 * pannello di default (resta l'admin). Il team id di spatie/permission viene
 * allineato al tenant corrente dal SetPermissionsTeamFromTenant (tenant
 * middleware persistente), così i check di autorizzazione sono tenant-scoped.
 *
 * Autenticazione (Fase 1, slice 8):
 * - login()/registration(): accesso e self-registration dell'account.
 * - passwordReset(): richiesta e reset password via email (Mailpit in locale).
 * - emailVerification(): la verifica email è OBBLIGATORIA su questo pannello.
 *   Filament aggiunge il middleware `verified` alle rotte di pagine/resource,
 *   quindi un utente con email non verificata viene rediretto alla prompt di
 *   verifica. È panel-scoped: l'AdminPanelProvider NON abilita la verifica,
 *   perciò l'accesso admin resta invariato.
 * - profile(): pagina account (nome/email/password) per l'utente autenticato.
 *
 * 2FA/MFA: differita alla fase di hardening. I provider MFA di Filament 5
 * (App/TOTP ed Email) richiedono storage persistente sulla tabella users
 * (secret, recovery codes, flag) → migrazioni/colonne non in scope qui.
 */
class ShelterPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('shelter')
            ->path('manage')
            ->login()
            ->registration()
            ->passwordReset()
            ->emailVerification()
            ->profile()
            ->colors([
                'primary' => Color::Emerald,
            ])
            ->tenant(Organization::class, slugAttribute: 'slug')
            ->tenantRegistration(RegisterOrganization::class)
            ->discoverResources(in: app_path('Filament/Shelter/Resources'), for: 'App\Filament\Shelter\Resources')
            ->discoverPages(in: app_path('Filament/Shelter/Pages'), for: 'App\Filament\Shelter\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Shelter/Widgets'), for: 'App\Filament\Shelter\Widgets')
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
            ])
            ->tenantMiddleware([
                SetPermissionsTeamFromTenant::class,
            ], isPersistent: true);
    }
}
