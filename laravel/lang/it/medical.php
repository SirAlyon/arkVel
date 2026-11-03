<?php

declare(strict_types=1);

return [
    // Promemoria allo staff dell'ente per una scadenza sanitaria in arrivo/scaduta.
    'reminder' => [
        'subject' => 'Scadenza sanitaria in arrivo per :animal',
        'greeting' => 'Ciao,',
        'line' => 'La voce :type di :animal è in scadenza il :due. Ricordati di programmarla.',
        'action' => 'Vai alla dashboard',
        'salutation' => 'A presto, il team di ArkVel',
        // Messaggio breve per la notifica in-app (canale database).
        'database' => ':type di :animal in scadenza il :due.',
        // Riepilogo stampato dal comando arkvel:send-medical-reminders.
        'command_summary' => 'Promemoria sanitari inviati: :count.',
    ],

    // Widget "Scadenze sanitarie in arrivo" della dashboard shelter.
    'widget' => [
        'heading' => 'Scadenze sanitarie in arrivo',
        'description' => 'Richiami e controlli in scadenza (o già scaduti) nei prossimi 30 giorni.',
        'animal' => 'Animale',
        'type' => 'Tipo',
        'next_due_at' => 'Scadenza',
        'vet_name' => 'Veterinario',
        'empty_title' => 'Nessuna scadenza in arrivo',
        'empty_body' => 'Non ci sono scadenze sanitarie nei prossimi 30 giorni.',
    ],
];
