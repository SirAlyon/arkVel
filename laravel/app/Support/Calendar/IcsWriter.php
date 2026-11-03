<?php

declare(strict_types=1);

namespace App\Support\Calendar;

use Illuminate\Support\Carbon;

/**
 * Piccolo generatore RFC-5545 (VCALENDAR/VEVENT) fatto in casa.
 *
 * PERCHÉ NON UNA LIBRERIA: spatie/icalendar-generator ^3.3 (l'unica versione
 * compatibile con lo stack PHP 8.4 / Laravel 13 di ArkVel) NON espone la proprietà
 * LOCATION, che Cal-3 richiede (nome ente + città). Le versioni successive che la
 * offrono trascinano dipendenze incompatibili. Un generatore mirato — poche
 * proprietà, tutte quelle che ci servono (UID/DTSTART/DTEND/SUMMARY/STATUS/
 * LOCATION/DESCRIPTION) — ci dà controllo pieno su escaping, folding e, soprattutto,
 * su QUALI dati finiscono nell'evento (minimizzazione GDPR). RFC-5545 è stabile e
 * l'output è verificato dai test (e apribile da Google/Apple/Proton/Outlook).
 *
 * Responsabilità UNICA: serializzare valori già decisi altrove in testo iCalendar
 * conforme. Nessuna logica di dominio qui (quella vive in AppointmentCalendarExport):
 * questa classe conosce solo la grammatica del formato.
 *
 * Conformità:
 *  - terminatori di riga CRLF (\r\n) come impone la RFC;
 *  - escaping dei caratteri speciali nei valori TEXT (\\ ; , e newline);
 *  - "line folding" a 75 ottetti (continuazione con CRLF + spazio);
 *  - timestamp UTC nel form "yyyymmddThhmmssZ" (form 2 della RFC).
 */
final class IcsWriter
{
    /**
     * @var list<string>
     */
    private array $lines = [];

    public function __construct(
        private readonly string $productIdentifier,
    ) {}

    /**
     * Apre il VCALENDAR con le proprietà di intestazione obbligatorie/consigliate.
     * VERSION:2.0 e PRODID sono richieste; METHOD:PUBLISH marca il feed come
     * pubblicazione (sola lettura) verso i client in sottoscrizione; CALSCALE
     * esplicita il calendario gregoriano.
     */
    public function beginCalendar(?string $name = null): self
    {
        $this->line('BEGIN', 'VCALENDAR');
        $this->line('VERSION', '2.0');
        $this->line('PRODID', $this->productIdentifier);
        $this->line('CALSCALE', 'GREGORIAN');
        $this->line('METHOD', 'PUBLISH');

        if ($name !== null && $name !== '') {
            // Nome del calendario (estensioni ampiamente supportate da Apple/Google).
            // Anche i valori di intestazione passano dall'escaping TEXT: un nome ente
            // può contenere virgole (es. "Rifugio Speranza, ODV").
            $this->line('X-WR-CALNAME', $this->escapeText($name));
            $this->line('NAME', $this->escapeText($name));
        }

        return $this;
    }

    public function endCalendar(): self
    {
        $this->line('END', 'VCALENDAR');

        return $this;
    }

    /**
     * Scrive un VEVENT completo. I valori arrivano GIÀ minimizzati dal chiamante:
     * qui li serializziamo soltanto. DTSTAMP (istante di generazione) è richiesto
     * dalla RFC; UID stabile e SEQUENCE consentono ai client di deduplicare e
     * aggiornare l'evento invece di duplicarlo a ogni fetch del feed.
     *
     * SEQUENCE deve CRESCERE a ogni modifica dell'evento: alcuni client rigorosi
     * (es. Outlook/Exchange) accettano l'aggiornamento di uno stesso UID solo se il
     * SEQUENCE in arrivo è maggiore di quello memorizzato. Il chiamante passa un
     * valore monotòno (tipicamente updated_at->getTimestamp()); default 0.
     */
    public function event(
        string $uid,
        Carbon $start,
        Carbon $end,
        string $summary,
        string $status,
        ?string $location = null,
        ?string $description = null,
        int $sequence = 0,
    ): self {
        $this->line('BEGIN', 'VEVENT');
        $this->line('UID', $uid);
        $this->line('DTSTAMP', $this->formatUtc(Carbon::now()));
        $this->line('DTSTART', $this->formatUtc($start));
        $this->line('DTEND', $this->formatUtc($end));
        $this->line('SUMMARY', $this->escapeText($summary));
        $this->line('STATUS', $status);
        $this->line('SEQUENCE', (string) max(0, $sequence));
        $this->line('TRANSP', 'OPAQUE');

        if ($location !== null && $location !== '') {
            $this->line('LOCATION', $this->escapeText($location));
        }

        if ($description !== null && $description !== '') {
            $this->line('DESCRIPTION', $this->escapeText($description));
        }

        $this->line('END', 'VEVENT');

        return $this;
    }

    /**
     * Serializza tutto in testo iCalendar con terminatori CRLF (RFC-5545).
     */
    public function toString(): string
    {
        return implode("\r\n", $this->lines)."\r\n";
    }

    /**
     * Aggiunge una content line "PROP:VALUE" applicando il line folding a 75 ottetti.
     */
    private function line(string $property, string $value): void
    {
        $this->lines[] = $this->fold($property.':'.$value);
    }

    /**
     * Timestamp in UTC nel form 2 della RFC ("yyyymmddThhmmssZ"): tutti gli istanti
     * del feed sono in UTC, così i client li riproiettano nel fuso dell'utente senza
     * ambiguità e senza dover emettere componenti VTIMEZONE.
     */
    private function formatUtc(Carbon $moment): string
    {
        return $moment->copy()->utc()->format('Ymd\THis\Z');
    }

    /**
     * Escaping dei valori TEXT (RFC-5545 §3.3.11): backslash, punto e virgola,
     * virgola e newline hanno un significato nella grammatica e vanno protetti.
     */
    private function escapeText(string $value): string
    {
        return str_replace(
            ['\\', ';', ',', "\r\n", "\n", "\r"],
            ['\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'],
            $value,
        );
    }

    /**
     * Line folding (RFC-5545 §3.1): nessuna content line può superare 75 ottetti;
     * le continuazioni iniziano con CRLF seguito da uno spazio. Ragioniamo per
     * BYTE (non caratteri) per non spezzare sequenze multibyte a metà.
     */
    private function fold(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $folded = '';
        $current = '';

        foreach (mb_str_split($line) as $char) {
            // Se aggiungere questo carattere sfora i 75 ottetti, chiudi la riga e
            // riparti con uno spazio di continuazione (che conta come 1 ottetto).
            if (strlen($current) + strlen($char) > 75) {
                $folded .= ($folded === '' ? '' : "\r\n").$current;
                $current = ' '.$char;

                continue;
            }

            $current .= $char;
        }

        return $folded.($folded === '' ? '' : "\r\n").$current;
    }
}
