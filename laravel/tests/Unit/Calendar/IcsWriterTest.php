<?php

declare(strict_types=1);

use App\Support\Calendar\IcsWriter;
use Illuminate\Support\Carbon;

/**
 * Copre la GRAMMATICA RFC-5545 del serializzatore: escaping dei valori TEXT e line
 * folding a 75 ottetti — i due rami che i test di dominio (nomi corti, senza
 * caratteri speciali) non esercitano, ma da cui dipende l'apertura del file su
 * Google/Apple/Proton/Outlook.
 */

/** @return list<string> Le content line "sfoldate" (continuazioni riunite). */
function unfoldLines(string $ics): array
{
    // RFC-5545 §3.1: una continuazione è CRLF seguito da uno spazio (o tab).
    $joined = str_replace(["\r\n ", "\r\n\t"], '', $ics);

    return explode("\r\n", trim($joined, "\r\n"));
}

it('escapes commas, semicolons, backslashes and newlines in TEXT values', function () {
    $ics = (new IcsWriter('-//Test//EN'))
        ->beginCalendar('Rifugio Speranza, ODV')
        ->event(
            uid: 'x@test',
            start: Carbon::parse('2026-01-01 10:00', 'UTC'),
            end: Carbon::parse('2026-01-01 11:00', 'UTC'),
            summary: 'Visita: Rex; con nota, e \\ backslash',
            status: 'CONFIRMED',
            location: 'Sede, Milano',
            description: "Riga uno\nRiga due",
        )
        ->endCalendar()
        ->toString();

    $lines = unfoldLines($ics);

    // Il valore SUMMARY ha gli speciali protetti (\, \; \\) e nessun a-capo grezzo.
    $summary = collect($lines)->first(fn (string $l): bool => str_starts_with($l, 'SUMMARY:'));
    expect($summary)->toBe('SUMMARY:Visita: Rex\\; con nota\\, e \\\\ backslash');

    // Il newline nella DESCRIPTION diventa la sequenza \n (non un CRLF reale).
    $description = collect($lines)->first(fn (string $l): bool => str_starts_with($l, 'DESCRIPTION:'));
    expect($description)->toBe('DESCRIPTION:Riga uno\\nRiga due');

    // Anche il nome del calendario (header) è escapato: la virgola è protetta.
    expect(collect($lines)->contains('X-WR-CALNAME:Rifugio Speranza\\, ODV'))->toBeTrue();
});

it('folds content lines so no physical line exceeds 75 octets', function () {
    $longAnimal = str_repeat('a', 200);

    $ics = (new IcsWriter('-//Test//EN'))
        ->beginCalendar('Ente')
        ->event(
            uid: 'x@test',
            start: Carbon::parse('2026-01-01 10:00', 'UTC'),
            end: Carbon::parse('2026-01-01 11:00', 'UTC'),
            summary: 'Visita: '.$longAnimal,
            status: 'CONFIRMED',
        )
        ->endCalendar()
        ->toString();

    // Nessuna riga fisica supera i 75 ottetti (RFC-5545 §3.1).
    foreach (explode("\r\n", $ics) as $physicalLine) {
        expect(strlen($physicalLine))->toBeLessThanOrEqual(75);
    }

    // Le continuazioni iniziano con uno spazio e, una volta riunite, ricostruiscono
    // il valore lungo originale senza perdite.
    $summary = collect(unfoldLines($ics))->first(fn (string $l): bool => str_starts_with($l, 'SUMMARY:'));
    expect($summary)->toBe('SUMMARY:Visita: '.$longAnimal);
});

it('increments SEQUENCE from the provided value and clamps negatives to zero', function () {
    $ics = (new IcsWriter('-//Test//EN'))
        ->beginCalendar('Ente')
        ->event(
            uid: 'x@test',
            start: Carbon::parse('2026-01-01 10:00', 'UTC'),
            end: Carbon::parse('2026-01-01 11:00', 'UTC'),
            summary: 'Visita: Rex',
            status: 'CONFIRMED',
            sequence: 1_700_000_000,
        )
        ->endCalendar()
        ->toString();

    expect(collect(unfoldLines($ics))->contains('SEQUENCE:1700000000'))->toBeTrue();
});
