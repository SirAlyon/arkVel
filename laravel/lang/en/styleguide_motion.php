<?php

declare(strict_types=1);

/*
 | Styleguide · "motion" group (resources/views/styleguide/groups/motion.blade.php).
 */

return [
    'demo_name' => 'Luna',

    'rules' => [
        'title' => 'Rules',
        'css' => 'CSS first: transitions, @starting-style and View Transitions. JavaScript only where CSS cannot reach.',
        'library' => 'Motion (MIT) for micro-interactions, loaded with a dynamic import only on first use (the favorite heart).',
        'tokens' => 'Durations and easings from the tokens only: interactions within 300 ms (--duration-slow), entrances with --duration-reveal, ease-out, ease-in-out and ease-pop.',
        'reduced' => 'With “reduce motion” nothing animates: CSS durations drop to zero and JavaScript animations do not start.',
        'layout' => 'Only opacity and transforms: no layout shift (CLS), and nothing above the fold or on the main image.',
    ],

    'favorite' => [
        'title' => 'Favorite heart',
        'description' => 'On click the heart pops (ease-pop, 300 ms) when it becomes a favorite and bounces when it empties. It is visual feedback only: the state is still the server’s.',
        'example' => 'Try the heart',
        'a11y_state' => 'The state lives in aria-pressed and in the filled or empty heart, not in the animation.',
        'a11y_reduced' => 'With “reduce motion” the heart changes state without animating.',
    ],

    'toast' => [
        'title' => 'Toast confirmation',
        'description' => 'The toast slides up and fades in (220 ms, ease-out); the type icon follows with a small pop (ease-pop). All CSS, with @starting-style.',
        'example' => 'Launch a confirmation',
        'launch' => 'Save',
        'sample_title' => 'Changes saved',
        'sample_description' => 'We will let you know when the organization replies.',
        'a11y_reduced' => 'With “reduce motion” the toast appears without movement; the screen reader announcement does not change.',
    ],

    'transition' => [
        'title' => 'Card → profile photo',
        'description' => 'Opening an animal’s profile from a card (catalog, home, organization page, similar animals), the photo moves and grows into the gallery. The Back button returns it to its card.',
        'example' => 'How to use it',
        'hint' => 'It is a wire:navigate View Transition: the card and the gallery share the same name, animal-photo-<id>. On departure only the photo leading to the destination keeps it, so the other cards do not fly across the screen. Browsers without View Transitions change page without animation.',
        'a11y_reduced' => 'With “reduce motion” Livewire skips the transition.',
        'a11y_unique' => 'The name must be unique in the page: the same animal cannot appear twice with an animated photo (photo-transition="false" on x-site.animal-card).',
    ],

    'reveal' => [
        'title' => 'Reveal on scroll',
        'description' => 'Sections and cards fade in and move up slightly as they enter the viewport; a row of cards comes in sequence.',
        'example' => 'Scroll down to here',
        'hint' => 'These blocks were below the fold on load: they appeared as they entered the viewport.',
        'list_label' => 'Reveal examples',
        'a11y_nojs' => 'Without JavaScript everything is visible: only the script adds the hidden state.',
        'a11y_fold' => 'Whatever is already in view on load is never hidden (no flashes, no delay on the main image).',
        'a11y_focus' => 'A block still hidden that receives keyboard focus appears at once; it is always visible in print.',
        'a11y_reduced' => 'With “reduce motion” nothing is hidden or animated.',
        'items' => [
            'one' => 'First',
            'two' => 'Second',
            'three' => 'Third',
            'four' => 'Fourth',
            'five' => 'Fifth',
            'six' => 'Sixth',
            'seven' => 'Seventh',
            'eight' => 'Eighth',
        ],
    ],
];
