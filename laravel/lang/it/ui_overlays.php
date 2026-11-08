<?php

declare(strict_types=1);

/*
 | Testi dei componenti x-ui del gruppo "overlays" (dialog, drawer, dropdown, tooltip, toast).
 */

return [
    'dialog' => [
        'close' => 'Chiudi la finestra',
    ],

    'drawer' => [
        'close' => 'Chiudi il pannello',
    ],

    'toast' => [
        'close' => 'Chiudi la notifica',
        // Prefisso letto dagli screen reader prima del titolo e annuncio nelle regioni live:
        // la punteggiatura sta qui, non nel codice.
        'prefix' => ':type:',
        'announcement' => ':type. :title. :description',
        'announcement_short' => ':type. :title',
        'types' => [
            'success' => 'Fatto',
            'warning' => 'Attenzione',
            'danger' => 'Errore',
            'info' => 'Informazione',
        ],
    ],

    'toaster' => [
        'label' => 'Notifiche',
    ],
];
