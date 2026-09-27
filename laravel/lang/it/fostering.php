<?php

declare(strict_types=1);

return [
    // Notifica ai membri dell'ente quando arriva una nuova richiesta di affido.
    'new_received' => [
        'subject' => 'Nuova richiesta di affido per :animal',
        'greeting' => 'Ciao,',
        'line' => ':carer ha inviato una richiesta di affido per :animal.',
        'action' => 'Gestisci gli affidi',
        'salutation' => 'A presto, il team di ArkVel',
        // Messaggio breve per la notifica in-app (canale database).
        'database' => ':carer ha chiesto l\'affido di :animal.',
    ],

    // Notifica all'affidatario quando lo stato della richiesta cambia.
    'status_changed' => [
        'subject' => 'Aggiornamento della tua richiesta di affido per :animal',
        'greeting' => 'Ciao :name,',
        'line' => 'Lo stato della tua richiesta di affido per :animal è passato a ":status".',
        'action' => 'Vedi la richiesta',
        'salutation' => 'A presto, il team di ArkVel',
        // Messaggio breve per la notifica in-app (canale database).
        'database' => 'La tua richiesta di affido per :animal è ora ":status".',
    ],

    // Notifica ai membri dell'ente quando un affidatario ritira la richiesta.
    'withdrawn' => [
        'subject' => 'Richiesta di affido ritirata per :animal',
        'greeting' => 'Ciao,',
        'line' => ':carer ha ritirato la richiesta di affido per :animal.',
        'action' => 'Gestisci gli affidi',
        'salutation' => 'A presto, il team di ArkVel',
        // Messaggio breve per la notifica in-app (canale database).
        'database' => ':carer ha ritirato la richiesta di affido per :animal.',
    ],

    // Notifica all'affidatario quando l'affido inizia.
    'placement_started' => [
        'subject' => 'Affido avviato: :animal è con te',
        'greeting' => 'Ciao :name,',
        'line' => 'L\'affido di :animal è iniziato: grazie per l\'accoglienza!',
        'action' => 'Vai al mio account',
        'salutation' => 'A presto, il team di ArkVel',
        // Messaggio breve per la notifica in-app (canale database).
        'database' => 'L\'affido di :animal è iniziato.',
    ],

    // Notifica all'affidatario quando l'affido si conclude.
    'placement_ended' => [
        'subject' => 'Affido concluso: :animal',
        'greeting' => 'Ciao :name,',
        'line' => 'L\'affido di :animal si è concluso. Grazie per il tuo aiuto!',
        'action' => 'Vai al mio account',
        'salutation' => 'A presto, il team di ArkVel',
        // Messaggio breve per la notifica in-app (canale database).
        'database' => 'L\'affido di :animal si è concluso.',
    ],
];
