<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scadenzario sanitario: ogni mattina alle 08:00 lo staff dell'ente riceve i
// promemoria delle scadenze sanitarie in arrivo (richiami vaccino, sverminazioni,
// controlli). Idempotente (reminder_sent_at): eseguirlo più volte non re-invia.
Schedule::command('arkvel:send-medical-reminders')->dailyAt('08:00');

// Promemoria appuntamenti: ogni ora avvisa i richiedenti degli appuntamenti
// confermati imminenti (finestra di default 24h). Idempotente (reminder_sent_at).
Schedule::command('arkvel:send-appointment-reminders')->hourly();

// Sync bidirezionale (pull): ogni 15 min tira le modifiche fatte sui calendari
// esterni (Google/CalDAV) e le riconcilia negli appuntamenti. withoutOverlapping
// evita sovrapposizioni se un giro dura più del previsto.
Schedule::command('arkvel:sync-calendars')->everyFifteenMinutes()->withoutOverlapping();
