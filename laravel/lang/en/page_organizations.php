<?php

declare(strict_types=1);

/*
 | Public organization pages (UI-6d): the /enti index, the organization page with the
 | donation widget, the Checkout return pages. Existing copy stays in
 | public.organizations.*, public.organization.* and donations.public.*.
 */
return [
    'index' => [
        'eyebrow' => 'The people who care for them',
        'filters' => [
            'title' => 'Filter organizations',
            'search' => 'Search by name',
            'search_placeholder' => 'E.g. Il Faro Shelter',
            'region' => 'Region',
            'region_all' => 'All regions',
            'type' => 'Type of organization',
            'type_all' => 'All',
            'reset' => 'Clear filters',
            'apply' => 'Apply the filters',
        ],
        'results_title' => 'Organization list',
        'results' => '{0}No organizations found|{1}:count organization found|[2,*]:count organizations found',
        'empty_filtered' => [
            'title' => 'No organizations match your filters',
            'description' => 'Try another region or type of organization, or search for a different name.',
        ],
        'empty' => [
            'title' => 'No organizations yet',
            'catalog' => 'Browse the catalog',
        ],
    ],

    'show' => [
        'verified' => 'Verified organization',
        'location' => 'Based in:',
        'contacts_title' => 'Contacts',
        'email' => 'Email:',
        'phone' => 'Phone:',
        'stats_title' => 'The organization in numbers',
        'stats' => [
            'available' => '{1}animal looking for a home|[0,*]animals looking for a home',
            'adopted' => '{1}completed adoption|[0,*]completed adoptions',
        ],
        'about_title' => 'About us',
        'animals_title' => 'Their animals',
        'animals_empty_title' => 'No animals up for adoption, for now',
        'browse_catalog' => 'Browse the catalog',
        'map' => [
            'title' => 'Where it is',
            'label' => 'Map of the area of :name',
            'description' => 'The circle shows the area where the organization is, not its address. To visit, contact the organization.',
        ],
    ],

    'donation' => [
        'monthly_note' => 'Charged once a month, cancel anytime from your account.',
        'login' => 'Sign in',
        'amount_help' => 'From :min to :max.',
    ],

    'thanks' => [
        'next_title' => 'Want to do even more?',
        'next_body' => 'Take a look at the animals looking for a home: one of them might be waiting for you.',
        'catalog' => 'Browse the catalog',
    ],

    'cancelled' => [
        'help' => 'Your support for :name still means a lot: you can try again from the organization page.',
        'catalog' => 'Go to the catalog',
    ],
];
