<?php

declare(strict_types=1);

/*
 | x-ui.map and x-ui.map.list (UI-7): MapLibre map with approximate positions, controls,
 | keyboard instructions, loading and error states, tile attribution.
 | Placeholders in braces ({title}, {count}…) are replaced by the map JavaScript.
 */
return [
    'instructions' => 'Drag or use the arrow keys to move, + and − to zoom. Tab reaches the markers: Enter opens the details (on a group, zooms in), Esc closes them.',
    'instructions_area' => 'Drag or use the arrow keys to move, + and − to zoom.',
    'loading' => 'Loading the map…',
    'error' => 'The map could not be loaded.',
    'noscript' => 'The map needs JavaScript.',

    'marker' => [
        'label' => '{title}: {count}',
        'action' => 'See the animals',
        // Screen readers only, after «action»: «See the animals at Rifugio Aurora».
        'action_context' => 'at {title}',
    ],

    'cluster' => [
        'label' => '{animals} animals in {organizations} nearby organizations',
        'title' => 'Organizations in this area',
    ],

    'controls' => [
        'zoom_in' => 'Zoom in',
        'zoom_out' => 'Zoom out',
        'popup_close' => 'Close the details',
        'gestures_windows' => 'Use Ctrl + scroll to zoom the map',
        'gestures_mac' => 'Use ⌘ + scroll to zoom the map',
        'gestures_mobile' => 'Use two fingers to move the map',
    ],

    'attribution' => [
        'label' => 'Map:',
        'openfreemap' => 'OpenFreeMap',
        'openmaptiles' => '© OpenMapTiles',
        'openstreetmap' => '© OpenStreetMap contributors',
    ],

    'list' => [
        'unlocated' => 'Location not available',
    ],
];
