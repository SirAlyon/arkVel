<?php

declare(strict_types=1);

/*
 | Testi dei componenti x-ui del gruppo "contenuti" (resources/views/components/ui):
 | tabs, accordion, skeleton, empty-state, avatar.
 */

return [
    'skeleton' => [
        'loading' => 'Caricamento in corso…',
    ],

    'empty_state' => [
        'search' => [
            'title' => 'Nessun risultato',
            'description' => 'Nessun animale corrisponde alla ricerca. Prova a togliere qualche filtro o a cercare in una zona più ampia.',
        ],
        'favorites' => [
            'title' => 'Ancora nessun preferito',
            'description' => 'Tocca il cuore sulla scheda di un animale per ritrovarlo qui.',
        ],
        'error' => [
            'title' => 'Qualcosa è andato storto',
            'description' => 'Non siamo riusciti a caricare i contenuti. Riprova tra qualche istante.',
        ],
    ],

    'avatar' => [
        'more' => '{1} e un altro|[2,*] e altri :count',
    ],
];
