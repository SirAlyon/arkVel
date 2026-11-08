<?php

declare(strict_types=1);

/*
 | Testi dei componenti x-ui del gruppo "contenuti" (resources/views/components/ui):
 | tabs, accordion, skeleton, empty-state, avatar.
 */

return [
    'skeleton' => [
        'loading' => 'Loading…',
    ],

    'empty_state' => [
        'search' => [
            'title' => 'No results',
            'description' => 'No animal matches your search. Try removing a few filters or searching a wider area.',
        ],
        'favorites' => [
            'title' => 'No favorites yet',
            'description' => 'Tap the heart on an animal’s page to find it here.',
        ],
        'error' => [
            'title' => 'Something went wrong',
            'description' => 'We couldn’t load the content. Please try again in a moment.',
        ],
    ],

    'avatar' => [
        'more' => '{1} and one more|[2,*] and :count more',
    ],
];
