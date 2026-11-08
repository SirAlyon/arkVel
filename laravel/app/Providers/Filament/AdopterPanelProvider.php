<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use App\Http\Middleware\AuthenticateAdopter;
use App\Http\Middleware\EnsureAdopterRole;
use Filament\Actions\Action;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Contracts\View\View;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\View as ViewFacade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Pannello degli adottanti su /account.
 *
 * NON multi-tenant: l'adottante è una persona fisica, non un membro d'ente.
 * L'isolamento dei dati NON passa dalla tenancy (assente qui) ma da:
 *  - OWNERSHIP: l'AdopterProfilePolicy autorizza solo il proprietario del
 *    profilo (confronto `is()` sull'utente), sia per view sia per update. Un
 *    adottante non può vedere né modificare il profilo altrui.
 *  - VERIFICA EMAIL: obbligatoria su tutte le rotte autenticate del pannello
 *    (middleware `verified` di Laravel nell'authMiddleware).
 * Non è il pannello di default (resta l'admin).
 *
 * Autenticazione (dalla fase UI-5, docs/PIANO-UI-FRONTEND.md §5): accesso,
 * registrazione, reset password e verifica email sono pagine PUBBLICHE Livewire
 * (route login, register, password.*, verification.*; Action in app/Actions/Auth).
 * Il pannello non abilita più login(), registration(), passwordReset() né
 * emailVerification(); resta solo:
 * - profile(): pagina account (nome/email/password) per l'utente autenticato;
 * - AuthenticateAdopter: gli ospiti vanno a /accedi (intended = la pagina chiesta);
 * - EnsureEmailIsVerified (`verified`): l'utente non verificato va a /verifica-email;
 * - l'authMiddleware (questi due ed EnsureAdopterRole) è PERSISTENTE per Livewire: vale
 *   anche sulle richieste /livewire/update delle pagine del pannello;
 * - "Esci" nel menu utente: la route pubblica `logout` (LogoutUser), che torna alla
 *   home del sito invece che a una login del pannello.
 * I pannelli /manage e /admin tengono il login di Filament.
 *
 * Ruolo Adopter: assegnato TEAM-INDEPENDENT dal middleware EnsureAdopterRole a
 * ogni accesso autenticato (idempotente), prima della verifica email come quando
 * la prompt di verifica era una pagina del pannello; la registrazione pubblica lo
 * assegna già alla creazione dell'account. È bookkeeping/segmentazione: il ruolo
 * non porta permessi, l'autorizzazione è per ownership (vedi sopra).
 */
class AdopterPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('adopter')
            ->path('account')
            ->profile()
            ->userMenuItems([
                'logout' => fn (Action $action): Action => $action->url(route('logout')),
            ])
            ->colors([
                'primary' => Color::Rose,
            ])
            // Campanella notifiche in-app (canale database, tabella `notifications`):
            // l'adottante vede qui i cambi di stato candidatura e la conferma di
            // adozione. Polling ogni 30s per l'aggiornamento del badge senza reload.
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            // Carica Echo (Reverb) nel pannello: la campanella va live via websocket
            // (evento `database-notifications.sent`), col polling 30s come fallback.
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): View => ViewFacade::make('filament.realtime-assets'),
            )
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
            // Persistenti (isPersistent): Livewire li riapplica a ogni richiesta
            // /livewire/update dei componenti di /account. Livewire confronta il nome
            // ESATTO della classe e Filament registra come persistente solo il proprio
            // Authenticate, non la sottoclasse AuthenticateAdopter: senza questo flag un
            // ospite (o una sessione chiusa) potrebbe eseguire le azioni di una pagina
            // /account rimandando uno snapshot già emesso.
            ->authMiddleware([
                // Ospiti → /accedi (pagina pubblica, route `login`).
                AuthenticateAdopter::class,
                // Assegna il ruolo Adopter (team-independent) agli utenti
                // autenticati: gira DOPO l'autenticazione, così l'utente esiste.
                EnsureAdopterRole::class,
                // Email non verificata → /verifica-email (route `verification.notice`).
                EnsureEmailIsVerified::class,
            ], isPersistent: true);
    }
}
