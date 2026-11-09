<?php

declare(strict_types=1);

/*
 | Styleguide · gruppo "map" (resources/views/styleguide/groups/map.blade.php).
 */
return [
    'map' => [
        'title' => 'Mappa',
        'description' => 'MapLibre GL JS con tile vettoriali OpenFreeMap, caricato con import dinamico solo quando la mappa sta per entrare in vista. Le posizioni sono sempre approssimate (griglia di circa 2 km, configurabile): il server non manda mai le coordinate salvate né l’indirizzo.',
        'markers' => 'Segnaposto con conteggio e cluster',
        'markers_label' => 'Mappa di esempio degli enti',
        'fallback' => 'Gli stessi enti sono nell’elenco qui sotto.',
        'area' => 'Zona approssimata (pagina dell’ente)',
        'area_label' => 'Mappa di esempio della zona di un ente',
        'a11y_name' => 'Il canvas ha un nome (aria-label) e le istruzioni da tastiera visibili collegate con aria-describedby.',
        'a11y_keyboard' => 'Frecce per spostarsi, + e − per lo zoom; i segnaposto sono bottoni da 44 px: Invio apre il riquadro, Esc lo chiude e riporta il focus.',
        'a11y_list' => 'La mappa non sostituisce i dati: accanto c’è sempre un elenco o un testo con le stesse informazioni.',
        'a11y_fallback' => 'Senza JavaScript o se lo stile non si carica resta un messaggio (regione live), la mappa sparisce.',
        'a11y_motion' => 'Con «riduci movimento» gli spostamenti sono immediati (jumpTo), niente voli.',
    ],

    'list' => [
        'title' => 'Elenco della mappa',
        'description' => 'Gli stessi segnaposto della mappa in una lista di link: per tastiera, screen reader e per chi non vede la mappa.',
        'example' => 'Elenco alternativo',
        'label' => 'Elenco di esempio degli enti',
        'a11y_same' => 'Stessi dati e stesso ordine della mappa; il numero in evidenza è decorativo, il testo lo ripete.',
        'a11y_unlocated' => 'Chi non ha una posizione compare solo qui, con «Posizione non disponibile».',
    ],

    'switch' => [
        'title' => 'Selettore di vista',
        'description' => 'Pillole per passare da una vista all’altra (es. «Elenco / Mappa» nel catalogo).',
        'example' => 'Elenco e mappa',
        'a11y_links' => 'Ogni voce è un link vero: senza JavaScript cambia pagina; con Livewire imposta la proprietà.',
        'a11y_current' => 'La voce attiva ha aria-current e il fondo pieno, non solo un colore diverso.',
        'a11y_target' => 'Voci alte almeno 44 px, nel gruppo con un nome (role="group").',
    ],
];
