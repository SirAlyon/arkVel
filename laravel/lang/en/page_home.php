<?php

declare(strict_types=1);

/*
 | Public home page copy (UI-6a, App\Livewire\Home, livewire/home.blade.php).
 */

return [
    'hero' => [
        'badge' => 'Responsible adoption',
        'title' => 'Find your next best friend',
        'lead' => 'Dogs, cats and other animals are waiting for a family at verified shelters, rescues and associations. Every adoption changes two lives.',
        'search_label' => 'Search the catalog for an animal',
        'search_placeholder' => 'Name, breed or city',
        'search_submit' => 'Search',
        'browse' => 'Or browse the whole catalog',
        'spotlight_label' => 'Meet',
        'spotlight_action' => 'Meet :name',
        'spotlight_alt' => 'Photo of :name',
        'stats_label' => 'ArkVel in numbers',
    ],

    'stats' => [
        'animals' => 'animal looking for a home|animals looking for a home',
        'organizations' => 'verified organization|verified organizations',
        'adoptions' => 'adoption completed on ArkVel|adoptions completed on ArkVel',
    ],

    'showcase' => [
        'eyebrow' => 'Showcase',
        'title' => 'Looking for a home',
        'description' => 'Some of the friends waiting for you, picked by the organizations.',
        'see_all' => 'See all',
        'filter_label' => 'Show only one species',
        'all' => 'All',
        'species' => [
            'dog' => 'Dogs',
            'cat' => 'Cats',
            'other' => 'Other animals',
        ],
        'results' => '{0} No animals to show|{1} One animal in the showcase|[2,*] :count animals in the showcase',
        'results_species' => '{0} No animals to show: :species|{1} One animal in the showcase: :species|[2,*] :count animals in the showcase: :species',
        'empty_filtered_title' => 'No other animals of this species in the showcase',
        'empty_filtered_body' => 'In the full catalog you can search by size, sex and city.',
        'empty_title' => 'There are no animals up for adoption yet',
        'empty_body' => 'Organizations are preparing their listings: come back soon.',
        'empty_action' => 'Go to the catalog',
    ],

    'how' => [
        'eyebrow' => 'How it works',
        'title' => 'Adopting, step by step',
        'description' => 'We walk with you from the first search to the day your new friend comes home.',
        'steps' => [
            'browse' => [
                'title' => 'Explore',
                'body' => 'Browse the available animals and filter by species, size and city.',
            ],
            'apply' => [
                'title' => 'Apply',
                'body' => 'Found the right one? Send your application to the organization caring for them.',
            ],
            'meet' => [
                'title' => 'Get to know each other',
                'body' => 'The organization contacts you for an interview and a visit, to see if you are a good match.',
            ],
            'welcome' => [
                'title' => 'Welcome home',
                'body' => 'Once the adoption contract is signed, you bring your new companion home.',
            ],
        ],
    ],

    'stories' => [
        'eyebrow' => 'Happy endings',
        'title' => 'The first stories are still to be written',
        'body' => 'This is where we will tell the adoptions that started on ArkVel, only with the consent of the families and the organizations. No made-up stories: we are waiting for yours.',
        'action' => 'Write the next one',
    ],

    'support' => [
        'title' => 'Support the people who care for them',
        'body' => 'Shelters, rescues and associations care for and house animals every day. A donation to the organization you choose helps them do it.',
        'action' => 'Make a donation',
    ],

    'organizations' => [
        'title' => 'Do you run a shelter, a rescue or an association?',
        'body' => 'List your animals, receive applications and schedule visits in one place.',
        'action' => 'Register your organization',
    ],
];
