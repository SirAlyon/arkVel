<?php

declare(strict_types=1);

/*
 | Public animal page copy (UI-6c, App\Livewire\AnimalDetail,
 | livewire/animal-detail.blade.php and livewire/animal-detail/*). The copy of each call
 | to action (application, foster, booking, contact) stays in its domain file
 | (applications, fostering, appointments, messaging).
 */

return [
    'age' => [
        'months' => '{1}:count month|[2,*]:count months',
        'years' => '{1}:count year|[2,*]:count years',
    ],

    'header' => [
        'traits' => 'About :name at a glance',
        'location' => 'Location:',
        'organization' => 'Organization:',
        'size' => ':size size',
    ],

    'about' => [
        'title' => 'Meet :name',
        'empty' => 'The organization has not written an introduction to :name yet: feel free to ask them in a message.',
    ],

    'facts' => [
        'title' => 'Good to know about :name',
        'tabs' => 'Information about :name',
        'tab' => [
            'profile' => 'Profile',
            'character' => 'Character',
            'compatibility' => 'Compatibility',
            'health' => 'Health',
        ],
        'species' => 'Species',
        'breed' => 'Breed',
        'sex' => 'Sex',
        'age' => 'Age',
        'size' => 'Size',
        'temperament' => 'Temperament',
        'energy' => 'Energy',
        'kids' => 'With children',
        'dogs' => 'With dogs',
        'cats' => 'With cats',
        'compatible' => [
            'yes' => 'Gets along',
            'no' => 'Better not',
            'unknown' => 'To be assessed',
        ],
        'vaccinated' => 'Vaccinations',
        'sterilized' => 'Spaying/neutering',
        'dewormed' => 'Deworming',
        'done' => 'Done',
        'not_done' => 'Not yet',
        'not_specified' => 'Not specified',
        'health_note' => 'Ask the organization for the full health history: they share it with applicants.',
        'compatibility_note' => '“To be assessed” means the organization has not seen it put to the test yet: just ask.',
    ],

    'organization' => [
        'title' => 'Who takes care of :name',
    ],

    'actions' => [
        'meet_title' => 'Before you apply',
        'meet_body' => 'Meet :name in person or ask the organization a question.',
        'book' => 'Book a visit',
        'contact' => 'Message the organization',
        'bar_open' => 'Adopt or meet',
        'sheet_title' => 'Adopt or meet :name',
        'sheet_description' => 'Apply, offer to foster, book a visit or message the organization.',
    ],

    'booking' => [
        'when' => 'Date and time',
        'when_help' => 'Pick the day and time you prefer: the organization will confirm the appointment.',
        'date' => 'Visit day',
        'time' => 'Visit time',
        'guest_note' => 'You need an account to book a visit: after signing in you come back here.',
    ],

    'contact' => [
        'guest_note' => 'You need an account to message the organization: after signing in you come back here.',
    ],

    'guest' => [
        'login' => 'Sign in',
    ],

    'similar' => [
        'title' => 'You might also like',
        'description' => 'Other animals of the same species or from the same organization.',
        'browse' => 'Browse the catalog',
    ],
];
