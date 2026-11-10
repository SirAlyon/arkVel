<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\SendEmailVerificationController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\CalendarFeedController;
use App\Http\Controllers\GoogleCalendarConnectController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\LogoutController;
use App\Http\Controllers\RobotsTxtController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\StyleguideGroupController;
use App\Http\Middleware\EnsureLocalEnvironment;
use App\Livewire\AnimalCatalog;
use App\Livewire\AnimalDetail;
use App\Livewire\Auth\ForgotPasswordPage;
use App\Livewire\Auth\LoginPage;
use App\Livewire\Auth\RegisterPage;
use App\Livewire\Auth\ResetPasswordPage;
use App\Livewire\Auth\VerifyEmailPage;
use App\Livewire\DonationCancelled;
use App\Livewire\DonationThankYou;
use App\Livewire\Home;
use App\Livewire\Legal\PrivacyPolicy;
use App\Livewire\Legal\TermsOfService;
use App\Livewire\OrganizationIndex;
use App\Livewire\OrganizationPage;
use Illuminate\Support\Facades\Route;

/*
 | Sito pubblico (catalogo adozioni). Componenti Livewire full-page.
 |
 | Schema URL:
 |  - /                         home
 |  - /animali                  catalogo (ricerca + filtri, query string)
 |  - /animali/{animal}         scheda animale (binding per ID: lo slug è
 |                              univoco solo per-ente, non globalmente)
 |  - /enti                     elenco enti verificati
 |  - /enti/{organization:slug} pagina pubblica dell'ente
 |
 | La visibilità (Available + ente Verified) è imposta nei componenti tramite
 | lo scope Animal::publiclyVisible() / Organization::verified().
 */

Route::get('/', Home::class)->name('home');

/*
 | SEO (UI-3): sitemap delle pagine pubbliche indicizzabili e robots.txt generato
 | (l'URL della sitemap deve essere assoluto e viene da APP_URL). Vedi App\Support\Seo.
 | Fuori dallo stack `web` (sessione, cookie, CSRF, SetLocale): sono documenti statici per
 | i crawler, uguali per tutti, e non devono aprire una sessione né impostare cookie.
 */
Route::get('/sitemap.xml', SitemapController::class)->withoutMiddleware('web')->name('seo.sitemap');
Route::get('/robots.txt', RobotsTxtController::class)->withoutMiddleware('web')->name('seo.robots');

/*
 | Autenticazione dell'adottante sul sito pubblico (UI-5): pagine Livewire con le route
 | standard di Laravel (login, register, password.*, verification.*), usate anche dal
 | middleware `auth` (redirectGuestsTo) e `verified` e dal pannello /account, che non ha
 | più login, registrazione, reset né prompt di verifica propri. /manage e /admin tengono
 | il login di Filament. La logica è nelle Action di app/Actions/Auth.
 |
 |  - /accedi, /registrati, /password-dimenticata, /reimposta-password/{token}: solo
 |    ospiti (`guest`: chi è già dentro va a /account, come le pagine di Filament);
 |  - /verifica-email: avviso per l'utente da verificare; il link della mail
 |    (/verifica-email/{id}/{hash}) è firmato e limitato, come il reinvio (POST).
 | Rate limit dei moduli: nelle Action (per email + IP, per IP o per utente).
 */
Route::middleware('guest')->group(function (): void {
    Route::get('/accedi', LoginPage::class)->name('login');
    Route::get('/registrati', RegisterPage::class)->name('register');
    Route::get('/password-dimenticata', ForgotPasswordPage::class)->name('password.request');
    Route::get('/reimposta-password/{token}', ResetPasswordPage::class)->name('password.reset');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/verifica-email', VerifyEmailPage::class)->name('verification.notice');

    Route::get('/verifica-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('/verifica-email/reinvia', SendEmailVerificationController::class)
        ->middleware('throttle:6,1')
        ->name('verification.send');
});

/*
 | Uscita dal sito pubblico: chiude la sessione e torna alla home (POST con CSRF).
 | Non passa dai pannelli Filament (il logout del pannello adottante attraversa il suo
 | authMiddleware, che darebbe il ruolo Adopter anche allo staff degli enti). La usa anche
 | la voce "Esci" del menu utente del pannello /account (AdopterPanelProvider).
 | Per un ospite (sessione già scaduta) è un no-op che porta alla home.
 */
Route::post('/esci', LogoutController::class)->name('logout');

/*
 | Selettore lingua: salva la scelta in sessione (letta da SetLocale) e torna indietro.
 | {locale} è risolto sull'enum AppLocale: un codice non supportato risponde 404.
 */
Route::post('/lingua/{locale}', LocaleController::class)->name('locale.update');

/*
 | Styleguide del design system (token e componenti x-ui): solo in `local`,
 | altrove 404 (EnsureLocalEnvironment). Vedi docs/PIANO-UI-FRONTEND.md §5 UI-1.
 */
Route::middleware(EnsureLocalEnvironment::class)->group(function (): void {
    Route::view('/_styleguide', 'styleguide')->name('styleguide');

    // Una pagina per gruppo di componenti x-ui (App\Support\Styleguide\StyleguideGroups).
    Route::get('/_styleguide/{group}', StyleguideGroupController::class)
        ->where('group', '[a-z-]+')
        ->name('styleguide.group');
});

/*
 | Pagine legali pubbliche: informativa privacy (GDPR art. 13) e termini di servizio.
 | Contenuto statico reso come le altre pagine pubbliche (layout `app`, tema, SEO). I link
 | nel footer e la casella di presa visione in /registrati compaiono quando queste route
 | esistono (App\Support\Site\LegalLinks). Indicizzabili.
 */
Route::get('/privacy', PrivacyPolicy::class)->name('legal.privacy');
Route::get('/termini', TermsOfService::class)->name('legal.terms');

Route::get('/animali', AnimalCatalog::class)->name('catalog');
// Solo id numerici: /animali/luna risponde 404 (non 500 dal binding su una colonna intera).
Route::get('/animali/{animal}', AnimalDetail::class)->whereNumber('animal')->name('animals.show');

Route::get('/enti', OrganizationIndex::class)->name('organizations.index');
Route::get('/enti/{organization:slug}', OrganizationPage::class)->name('organizations.show');

/*
 | Donazioni una tantum (P4-6): landing di ritorno dal Checkout Stripe.
 |  - /enti/{slug}/grazie  pagina di ringraziamento (il pagamento può confermarsi
 |                         con un attimo di ritardo via webhook)
 |  - /enti/{slug}/annulla back all'ente con un flash localizzato (pagamento annullato)
 | L'avvio del Checkout avviene sulla pagina pubblica dell'ente (OrganizationPage),
 | config-gated su StripeConfig::configured().
 */
Route::get('/enti/{organization:slug}/grazie', DonationThankYou::class)->name('organizations.donate.thanks');
Route::get('/enti/{organization:slug}/annulla', DonationCancelled::class)->name('organizations.donate.cancel');

/*
 | Webhook Stripe (P4-6): conferma dei pagamenti di Checkout. POST pubblico (nessun
 | login: lo chiama Stripe), escluso dalla CSRF in bootstrap/app.php. La firma è
 | verificata nel controller contro StripeConfig::webhookSecret() quando impostato;
 | se vuoto (dev/test) la verifica è saltata. Idempotente e no-op sugli eventi ignoti.
 */
Route::post('/stripe/webhook', StripeWebhookController::class)->name('stripe.webhook');

/*
 | Connessione OAuth a Google Calendar (Cal-5).
 |
 |  - /calendar/google/redirect   avvia il consenso OAuth (autorizza sul tenant,
 |                                salva state+tenant in sessione, → Google)
 |  - /calendar/google/callback   riceve il code, crea/aggiorna la connessione Google
 |
 | Entrambe dietro `auth`. Girano FUORI dal contesto URL del pannello: il tenant è
 | risolto dalla sessione/parametro, MAI dall'URL, e `calendar.manage` è ri-verificato
 | con il team id allineato al tenant (nessun collegamento a un ente altrui).
 | Config-gated: senza credenziali OAuth la redirect risponde 404.
 |
 | IMPORTANTE: queste rotte specifiche vanno DICHIARATE PRIMA del feed wildcard
 | `/calendar/{organization}/{token}`, altrimenti quest'ultimo cattura
 | `/calendar/google/redirect` (organization=google, token=redirect).
 */
Route::middleware('auth')->group(function (): void {
    Route::get('/calendar/google/redirect', [GoogleCalendarConnectController::class, 'redirect'])
        ->name('calendar.google.redirect');

    Route::get('/calendar/google/callback', [GoogleCalendarConnectController::class, 'callback'])
        ->name('calendar.google.callback');
});

/*
 | Export iCalendar (Cal-3).
 |  - feed d'ente in sottoscrizione: pubblico ma protetto da un SEGRETO nell'URL
 |    (confronto a tempo costante nel controller); rate-limited; ente risolto per
 |    slug/id nel controller (niente model binding: 404 su token errato indistinguibile).
 |  - download .ics del singolo evento: dietro auth + Policy view.
 */
Route::get('/calendar/{organization}/{token}', [CalendarFeedController::class, 'feed'])
    // I client di calendario fanno polling ogni 15-60 min: 20 richieste/minuto per IP
    // sono ampie per l'uso legittimo e limitano lo scraping di un URL segreto trapelato.
    ->middleware('throttle:20,1')
    ->name('calendar.feed');

// L'autorizzazione (view) è fatta NEL controller, dopo aver impostato il team di
// spatie/permission dall'ente dell'appuntamento: fuori dai pannelli Filament il team
// non è impostato, quindi non possiamo usare ->can() a livello di rotta (respingerebbe
// lo staff verificato con un 403). Vedi CalendarFeedController::appointment.
Route::get('/appointments/{appointment}/calendar.ics', [CalendarFeedController::class, 'appointment'])
    ->middleware('auth')
    ->name('appointments.ics');
