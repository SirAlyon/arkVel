<?php

declare(strict_types=1);

use App\Http\Controllers\CalendarFeedController;
use App\Livewire\AnimalCatalog;
use App\Livewire\AnimalDetail;
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

Route::get('/animali', AnimalCatalog::class)->name('catalog');
Route::get('/animali/{animal}', AnimalDetail::class)->name('animals.show');

Route::get('/enti', OrganizationIndex::class)->name('organizations.index');
Route::get('/enti/{organization:slug}', OrganizationPage::class)->name('organizations.show');

/*
 | Export iCalendar (Cal-3).
 |
 |  - /calendar/{organization}/{token}          feed d'ente in sottoscrizione
 |    Pubblico ma protetto da un SEGRETO nell'URL (confronto a tempo costante nel
 |    controller); rate-limited. L'ente è risolto per slug o id nel controller
 |    (niente model binding: il 404 su token errato deve restare indistinguibile).
 |  - /appointments/{appointment}/calendar.ics  download .ics del singolo evento
 |    Dietro auth + Policy (view): proprietario o staff verificato dell'ente.
 |
 | Entrambe delegano a CalendarFeedController → AppointmentCalendarExport (PII
 | minimizzata). La rotta del singolo evento è "calendar.ics" per usare route()
 | dalle pagine/notifiche; il feed è "calendar.feed".
 */
Route::get('/calendar/{organization}/{token}', [CalendarFeedController::class, 'feed'])
    // I client di calendario fanno polling ogni 15-60 min: 20 richieste/minuto per IP
    // sono ampie per l'uso legittimo e limitano lo scraping di un URL segreto trapelato.
    ->middleware('throttle:20,1')
    ->name('calendar.feed');

Route::get('/appointments/{appointment}/calendar.ics', [CalendarFeedController::class, 'appointment'])
    ->middleware('auth')
    ->can('view', 'appointment')
    ->name('appointments.ics');
