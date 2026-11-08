<?php

declare(strict_types=1);

/*
 | Testi dei componenti x-ui del gruppo "dominio" (resources/views/components/ui):
 | animal-card (+ favorite, cta, skeleton), organization-card.
 */

return [
    'animal_card' => [
        'photo_alt' => 'Photo of :name',
        'featured' => 'Featured',
        'size' => 'Size: :size',
        'location' => 'Located in',
        'loading' => 'Loading animals…',
        'favorite' => 'Save :name to favorites',
        'cta' => [
            'title' => 'Still looking for your new friend?',
            'description' => 'Many more animals in the catalog are waiting for a home.',
            'description_count' => '{0} New animals looking for a home arrive every week.|{1} One more animal in the catalog is waiting for a home.|[2,*] :count more animals in the catalog are waiting for a home.',
            'action' => 'Browse the catalog',
        ],
    ],

    'organization_card' => [
        'location' => 'Based in',
        'verified' => 'Verified',
        'available' => '{0} No animals available|{1} :count animal available|[2,*] :count animals available',
    ],
];
