<?php

declare(strict_types=1);

/*
 | x-ui.map e x-ui.map.list (UI-7): mappa MapLibre con posizioni approssimate, controlli,
 | istruzioni da tastiera, stati di caricamento ed errore, attribuzione delle tile.
 | I segnaposto tra graffe ({title}, {count}…) li sostituisce il JavaScript della mappa.
 */
return [
    'instructions' => 'Trascina o usa le frecce per spostarti, + e − per lo zoom. Tab porta ai segnaposto: Invio apre il riquadro (su un gruppo lo ingrandisce), Esc lo chiude.',
    'instructions_area' => 'Trascina o usa le frecce per spostarti, + e − per lo zoom.',
    'loading' => 'Caricamento della mappa…',
    'error' => 'Non è stato possibile caricare la mappa.',
    'noscript' => 'La mappa ha bisogno di JavaScript.',

    'marker' => [
        'label' => '{title}: {count}',
        'action' => 'Vedi gli animali',
        // Solo per gli screen reader, dopo «action»: «Vedi gli animali di Rifugio Aurora».
        'action_context' => 'di {title}',
    ],

    'cluster' => [
        'label' => '{animals} animali in {organizations} enti vicini',
        'title' => 'Enti in questa zona',
    ],

    'controls' => [
        'zoom_in' => 'Aumenta lo zoom',
        'zoom_out' => 'Riduci lo zoom',
        'popup_close' => 'Chiudi il riquadro',
        'gestures_windows' => 'Usa Ctrl + rotellina per lo zoom della mappa',
        'gestures_mac' => 'Usa ⌘ + rotellina per lo zoom della mappa',
        'gestures_mobile' => 'Usa due dita per spostare la mappa',
    ],

    'attribution' => [
        'label' => 'Mappa:',
        'openfreemap' => 'OpenFreeMap',
        'openmaptiles' => '© OpenMapTiles',
        'openstreetmap' => '© contributori di OpenStreetMap',
    ],

    'list' => [
        'unlocated' => 'Posizione non disponibile',
    ],
];
