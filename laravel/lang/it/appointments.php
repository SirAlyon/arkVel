<?php

declare(strict_types=1);

return [
    // Notifica ai membri dell'ente quando viene richiesto un nuovo appuntamento.
    'new_requested' => [
        'subject' => 'Nuovo appuntamento richiesto per :animal',
        'greeting' => 'Ciao,',
        'line' => ':requester ha richiesto un appuntamento (:type) per :animal il :when.',
        'action' => 'Gestisci gli appuntamenti',
        'salutation' => 'A presto, il team di ArkVel',
        // Messaggio breve per la notifica in-app (canale database).
        'database' => ':requester ha richiesto un appuntamento (:type) per :animal.',
    ],

    // Notifica al richiedente quando l'ente conferma l'appuntamento.
    'confirmed' => [
        'subject' => 'Appuntamento confermato per :animal',
        'greeting' => 'Ciao :name,',
        'line' => 'Il tuo appuntamento per :animal è stato confermato per il :when.',
        'action' => 'Vai al mio account',
        'salutation' => 'A presto, il team di ArkVel',
        // Messaggio breve per la notifica in-app (canale database).
        'database' => 'Il tuo appuntamento per :animal è confermato per il :when.',
    ],

    // Notifica alla controparte quando un appuntamento viene annullato.
    'cancelled' => [
        'subject' => 'Appuntamento annullato per :animal',
        'greeting' => 'Ciao,',
        'line' => 'L\'appuntamento per :animal del :when è stato annullato.',
        'reason' => 'Motivazione: :reason',
        'action' => 'Vai al mio account',
        'salutation' => 'A presto, il team di ArkVel',
        // Messaggio breve per la notifica in-app (canale database).
        'database' => 'L\'appuntamento per :animal del :when è stato annullato.',
    ],

    // Notifica al richiedente quando l'appuntamento è completato.
    'completed' => [
        'subject' => 'Appuntamento completato per :animal',
        'greeting' => 'Ciao :name,',
        'line' => 'Il tuo appuntamento per :animal è stato completato. Grazie!',
        'action' => 'Vai al mio account',
        'salutation' => 'A presto, il team di ArkVel',
        // Messaggio breve per la notifica in-app (canale database).
        'database' => 'Il tuo appuntamento per :animal è stato completato.',
    ],

    // Notifica al richiedente quando viene registrata la sua assenza.
    'no_show' => [
        'subject' => 'Assenza registrata per l\'appuntamento su :animal',
        'greeting' => 'Ciao :name,',
        'line' => 'Risulti assente all\'appuntamento per :animal del :when.',
        'action' => 'Vai al mio account',
        'salutation' => 'A presto, il team di ArkVel',
        // Messaggio breve per la notifica in-app (canale database).
        'database' => 'Risulti assente all\'appuntamento per :animal.',
    ],
];
