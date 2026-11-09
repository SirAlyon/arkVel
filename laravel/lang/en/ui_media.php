<?php

declare(strict_types=1);

/*
 | Copy of the x-ui components in the "media" group (resources/views/components/ui):
 | picture, gallery (+ PhotoSwipe lightbox).
 */

return [
    'gallery' => [
        'label' => 'Photos of :name',
        'roledescription' => 'carousel',
        'slide_roledescription' => 'photo',
        'slide' => ':position of :total',
        'photo_alt' => 'Photo :position of :total of :name',
        'open' => '(open full screen)',
        'previous' => 'Previous photo',
        'next' => 'Next photo',
        'counter' => 'Photo :position of :total',
        'thumbnails' => 'Choose a photo',
        'thumbnail' => 'Show photo :position of :total',
        'lightbox' => [
            'label' => 'Photos of :name, full screen',
            'close' => 'Close',
            'zoom' => 'Zoom in or out',
            'previous' => 'Previous photo',
            'next' => 'Next photo',
            'error' => 'The photo could not be loaded.',
        ],
    ],
];
