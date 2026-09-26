<?php

declare(strict_types=1);

return [
    // Notifica all'adottante quando lo stato della candidatura cambia.
    'status_changed' => [
        'subject' => 'Aggiornamento della tua candidatura per :animal',
        'greeting' => 'Ciao :name,',
        'line' => 'Lo stato della tua candidatura per :animal è passato a ":status".',
        'action' => 'Vedi la candidatura',
        'salutation' => 'A presto, il team di ArkVel',
        // Messaggio breve per la notifica in-app (canale database).
        'database' => 'La tua candidatura per :animal è ora ":status".',
    ],
];
