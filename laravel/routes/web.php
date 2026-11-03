<?php

declare(strict_types=1);

use App\Http\Controllers\CalendarFeedController;
use App\Http\Controllers\GoogleCalendarConnectController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\StripeWebhookController;
use App\Livewire\AnimalCatalog;
use App\Livewire\AnimalDetail;
use App\Livewire\DonationCancelled;
use App\Livewire\DonationThankYou;
use App\Livewire\Home;
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
 | Selettore lingua: salva la scelta in sessione (letta da SetLocale) e torna indietro.
 | {locale} è risolto sull'enum AppLocale: un codice non supportato risponde 404.
 */
Route::post('/lingua/{locale}', LocaleController::class)->name('locale.update');

Route::get('/animali', AnimalCatalog::class)->name('catalog');
Route::get('/animali/{animal}', AnimalDetail::class)->name('animals.show');

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
