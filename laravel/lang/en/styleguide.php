<?php

declare(strict_types=1);

/*
 | Design system styleguide (/_styleguide, local environment only).
 */

return [
    'title' => 'Styleguide — ArkVel',
    'heading' => 'Styleguide',
    'intro' => 'The public site tokens (“Playful pop” direction): color, type, shape, spacing and motion, in light and dark themes.',
    'language' => 'Language',
    'toc' => 'Sections',

    'sections' => [
        'preview' => 'Preview',
        'colors' => 'Colors',
        'contrast' => 'Contrast',
        'typography' => 'Typography',
        'shapes' => 'Shapes',
        'spacing' => 'Spacing',
        'motion' => 'Motion',
    ],

    'preview' => [
        'disabled' => 'Disabled',
        'intro' => 'How the tokens combine in the home page blocks. Not a component yet: it is here to judge the direction in both themes.',
        'stamp' => 'Verified shelters across Europe',
        'headline' => 'Find your new best friend.',
        'lead' => 'Dogs, cats and many more are waiting for you. Apply in a few steps, meet them at the shelter.',
        'search_label' => 'Search for an animal',
        'search_placeholder' => 'Search by name, breed or city',
        'search' => 'Search',
        'photo' => 'Photo goes here',
        'stat_animals' => 'animals available',
        'stat_organizations' => 'verified organizations',
        'filters' => 'Filter by species',
        'all' => 'All',
        'dogs' => 'Dogs',
        'cats' => 'Cats',
        'others' => 'Others',
        'primary' => 'Apply',
        'secondary' => 'Sign in',
        'cta_card' => 'More friends to meet',
        'cta' => 'Browse the catalog',
        'donate' => 'Can’t adopt? Make a difference with a donation.',
        'donate_cta' => 'Donate to an organization',
    ],

    'colors' => [
        'on' => 'text: :token',
        'semantic' => 'Semantic tokens',
        'semantic_intro' => 'Use these in components: they change value with the theme. “Aa” shows the intended text on the background.',
        'palette' => 'Palette',
        'palette_intro' => 'OKLCH scales from 50 to 950. The mockup colors are anchored exactly; palette classes belong inside semantic tokens or documented cases only.',
        'groups' => [
            'backgrounds' => 'Backgrounds',
            'text' => 'Text and borders',
            'actions' => 'Actions',
            'blocks' => 'Color blocks',
            'status' => 'Status',
        ],
    ],

    'contrast' => [
        'intro' => 'Ratios computed by the browser for the active theme. Text needs at least 4.5:1; control borders and focus at least 3:1. ColorContrastTest checks the same pairs.',
        'pair' => 'Pair',
        'sample' => 'Sample',
        'ratio' => 'Ratio',
        'minimum' => 'Minimum',
        'result' => 'Result',
        'pass' => 'Passes',
        'fail' => 'Fails',
        'text' => 'Text',
        'non_text' => 'Border / focus',
        'forbidden' => 'Not allowed: yellow on green (4.43:1) for text under 24 px; the decorative `line` border (1.31:1) for inputs and controls.',
    ],

    'typography' => [
        'intro' => 'One family, variable Bricolage Grotesque (self-hosted, automatic optical size). Headings scale with the window.',
        'sample_title' => 'Every paw has a story',
        'sample_text' => 'Luna came to the shelter in spring. She loves long walks, gets on well with other dogs and is looking for a patient family.',
    ],

    'shapes' => [
        'pill' => 'pill',
        'intro' => 'Radii in order of hierarchy; buttons, chips and search fields are always pills. No shadows, except for floating layers.',
        'overlay' => 'Floating layer (shadow-overlay)',
    ],

    'spacing' => [
        'container' => 'max-w-page · maximum width 1240 px',
        'intro' => 'Fluid spacing for side gutters, panel padding and the gap between sections. Content is at most 1240 px wide.',
    ],

    'motion' => [
        'intro' => 'Shared durations and easing. Interactions stay within 300 ms; with “reduce motion” on, durations drop to zero.',
        'play' => 'Play',
        'reset' => 'Reset',
        'reduced' => 'Reduced motion is on: animations are disabled.',
    ],
];
