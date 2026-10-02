<?php

declare(strict_types=1);

return [
    // Notifica alla controparte quando arriva un nuovo messaggio in una
    // conversazione (stessa classe per adottante e staff dell'ente).
    'new_message' => [
        'subject' => 'Nuovo messaggio da :sender',
        'greeting' => 'Ciao,',
        'line' => ':sender ti ha inviato un nuovo messaggio:',
        'action' => 'Leggi il messaggio',
        'salutation' => 'A presto, il team di ArkVel',
        // Messaggio breve per la notifica in-app (canale database).
        'database' => ':sender ti ha inviato un nuovo messaggio.',
    ],
];
