<?php

declare(strict_types=1);

/*
 | Public site SEO (App\Support\Seo): titles, descriptions, breadcrumbs and
 | structured data copy. Home, catalog and organization index titles stay in
 | public.titles.* and are read from there.
 */

return [
    'site_name' => 'ArkVel',

    'titles' => [
        'animal' => ':name — ArkVel',
        'organization' => ':name — ArkVel',
        'page' => ':title — ArkVel',
        'catalog_paged' => 'Catalog, page :page — ArkVel',
        'organizations_paged' => 'Organizations, page :page — ArkVel',
        'organization_paged' => ':name, page :page — ArkVel',
    ],

    'descriptions' => [
        'catalog' => 'Dogs, cats and other animals up for adoption at verified shelters, rescues and associations. Search by species, size and sex.',
        'organizations' => 'Verified shelters, rescues and associations rehoming their animals on ArkVel.',
        'animal' => ':name (:species) is looking for a home: up for adoption at :organization.',
        'organization' => ':name (:type): meet the animals up for adoption and find out how to support them.',
        'donation_thanks' => 'Thank you for supporting :name.',
        'donation_cancelled' => 'Your donation to :name was cancelled.',
        'site' => 'Open-source platform for adopting animals from verified shelters, rescues and associations.',
    ],

    'breadcrumb' => [
        'label' => 'Breadcrumb',
        'home' => 'Home',
    ],
];
