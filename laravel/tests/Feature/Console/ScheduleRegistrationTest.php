<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Schedule;

/**
 * I due comandi di promemoria devono comparire nello scheduler applicativo
 * (routes/console.php). Risolviamo lo Schedule dal container e ispezioniamo i
 * comandi registrati sugli eventi.
 *
 * @return list<string>
 */
function scheduledCommands(): array
{
    $schedule = app(Schedule::class);

    return collect($schedule->events())
        ->map(fn ($event): string => (string) $event->command)
        ->all();
}

it('registers both reminder commands in the schedule', function () {
    $commands = scheduledCommands();

    $hasMedical = collect($commands)->contains(fn (string $c): bool => str_contains($c, 'arkvel:send-medical-reminders'));
    $hasAppointment = collect($commands)->contains(fn (string $c): bool => str_contains($c, 'arkvel:send-appointment-reminders'));

    expect($hasMedical)->toBeTrue()
        ->and($hasAppointment)->toBeTrue();
});
