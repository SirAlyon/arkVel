<?php

declare(strict_types=1);

/*
 | Styleguide · "map" group (resources/views/styleguide/groups/map.blade.php).
 */
return [
    'map' => [
        'title' => 'Map',
        'description' => 'MapLibre GL JS with OpenFreeMap vector tiles, loaded with a dynamic import only when the map is about to scroll into view. Locations are always approximate (a configurable grid of about 2 km): the server never sends the stored coordinates or the address.',
        'markers' => 'Markers with counts and clusters',
        'markers_label' => 'Sample map of organizations',
        'fallback' => 'The same organizations are in the list below.',
        'area' => 'Approximate area (organization page)',
        'area_label' => 'Sample map of the area of an organization',
        'a11y_name' => 'The canvas has a name (aria-label) and visible keyboard instructions linked with aria-describedby.',
        'a11y_keyboard' => 'Arrow keys move, + and − zoom; markers are 44 px buttons: Enter opens the details, Esc closes them and returns the focus.',
        'a11y_list' => 'The map does not replace the data: there is always a list or a text with the same information next to it.',
        'a11y_fallback' => 'Without JavaScript, or if the style cannot load, a message remains (live region) and the map goes away.',
        'a11y_motion' => 'With “reduce motion” moves are instant (jumpTo), no flying.',
    ],

    'list' => [
        'title' => 'Map list',
        'description' => 'The same markers as the map in a list of links: for keyboard, screen reader and anyone who cannot see the map.',
        'example' => 'Alternative list',
        'label' => 'Sample list of organizations',
        'a11y_same' => 'Same data and same order as the map; the highlighted number is decorative, the text repeats it.',
        'a11y_unlocated' => 'Those without a location appear only here, with “Location not available”.',
    ],

    'switch' => [
        'title' => 'View switch',
        'description' => 'Pills to move between views (e.g. “List / Map” in the catalog).',
        'example' => 'List and map',
        'a11y_links' => 'Each option is a real link: without JavaScript it changes page; with Livewire it sets the property.',
        'a11y_current' => 'The active option has aria-current and a filled background, not just a different color.',
        'a11y_target' => 'Options at least 44 px tall, in a named group (role="group").',
    ],
];
