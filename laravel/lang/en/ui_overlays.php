<?php

declare(strict_types=1);

/*
 | Testi dei componenti x-ui del gruppo "overlays" (dialog, drawer, dropdown, tooltip, toast).
 */

return [
    'dialog' => [
        'close' => 'Close dialog',
    ],

    'drawer' => [
        'close' => 'Close panel',
    ],

    'toast' => [
        'close' => 'Dismiss notification',
        // Prefisso letto dagli screen reader prima del titolo e annuncio nelle regioni live:
        // la punteggiatura sta qui, non nel codice.
        'prefix' => ':type:',
        'announcement' => ':type. :title. :description',
        'announcement_short' => ':type. :title',
        'types' => [
            'success' => 'Done',
            'warning' => 'Warning',
            'danger' => 'Error',
            'info' => 'Information',
        ],
    ],

    'toaster' => [
        'label' => 'Notifications',
    ],
];
