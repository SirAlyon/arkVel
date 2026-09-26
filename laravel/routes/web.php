<?php

declare(strict_types=1);

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
