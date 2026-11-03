<?php

declare(strict_types=1);

namespace App\Providers;

use App\Filament\Shelter\Pages\AppointmentCalendar;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Support\ServiceProvider;

/**
 * Registra gli asset di terze parti usati dalle pagine Filament dell'app.
 *
 * Oggi: la global build v6 di FullCalendar (MIT), che alimenta il calendario
 * appuntamenti del pannello ente ({@see AppointmentCalendar}).
 *
 * SCELTA DI CARICAMENTO — FilamentAsset + CDN, niente Vite/theme rebuild:
 *  - `Js::make($id, $urlRemota)` registra uno <script> CLASSICO (isModule=false di
 *    default), quindi la build `index.global.min.js` espone il global `window.FullCalendar`
 *    come richiesto dall'inizializzazione Alpine nella view. La v6 inietta da sé il
 *    proprio CSS: non serve un foglio di stile separato.
 *  - È un URL REMOTO: FilamentAsset lo emette così com'è (non lo copia in /public con
 *    `filament:assets`). Robusto, nessuna dipendenza dalla pipeline di build del tema.
 *  - VENDORING FUTURO: per togliere la dipendenza dalla CDN in produzione, scaricare il
 *    file in `resources/js/vendor/fullcalendar.global.js` e passare un path LOCALE a
 *    `Js::make(...)` (verrà pubblicato da `php artisan filament:assets`). L'API della
 *    pagina/della view non cambia.
 *
 * Registrato globalmente (su ogni pagina del pannello): semplice e sufficiente per
 * questo slice. FilamentAsset deduplica per id, quindi non ci sono doppioni.
 */
class CalendarAssetServiceProvider extends ServiceProvider
{
    /**
     * Versione pinnata della global build di FullCalendar (MIT). Pinnata di
     * proposito: aggiornamenti volontari, nessun drift dalla CDN.
     */
    private const string FULLCALENDAR_CDN = 'https://cdn.jsdelivr.net/npm/fullcalendar@6.1.15/index.global.min.js';

    public function boot(): void
    {
        FilamentAsset::register([
            Js::make('fullcalendar', self::FULLCALENDAR_CDN),
        ]);
    }
}
