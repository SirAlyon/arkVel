<?php

declare(strict_types=1);

/*
 | Styleguide · gruppo "dominio" (resources/views/styleguide/groups/domain.blade.php).
 */

return [
    'demo' => [
        'age_puppy' => '6 months',
        'age_adult' => '3 years',
        'age_senior' => '9 years',
        'see_all' => 'See all',
    ],

    'animal_card' => [
        'title' => 'Animal card',
        'description' => 'Surface with 12 px padding, rounded 4:5 photo on a rotating color block (sun, peach, mint), 800 name, badges and location. The whole card leads to the profile; the last card of the grid is dark with the yellow CTA.',
        'a11y_link' => 'A single link, on the name: its ::after covers the card, so it is clickable anywhere without empty links or buttons inside a link.',
        'a11y_favorite' => 'The favorite button is a sibling of the link, on top of it: its name contains the animal’s name and it has aria-pressed; filled or outlined, not just a color.',
        'a11y_heading' => 'The heading level is set with heading-level, so the page does not skip levels (h4 here, below the h3 of the examples).',
        'a11y_featured' => '“Featured” is a stamp with text and a star, never just a color.',
        'grid' => 'Catalog grid with favorite button',
        'grid_hint' => 'Rotating tones, with and without a photo. The heart here is <x-ui.animal-card.favorite> (static); pages use <livewire:favorite-button>, which has the same accessible name.',
        'grid_label' => 'Featured animals',
        'minimal' => 'Essential data only',
        'skeleton' => 'Loading',
        'skeleton_label' => 'Loading featured animals…',
    ],

    'organization_card' => [
        'title' => 'Organization card',
        'description' => 'Logo (or initials), name, location, organization type, “Verified” badge and available animals. Same stretched link as the animal card.',
        'a11y_link' => 'A single link, on the name, stretched over the whole card.',
        'a11y_logo' => 'The logo is decorative (empty alt), because the name is written next to it.',
        'a11y_count' => 'The number of animals is formatted in the current language and pluralized (none, one, many).',
        'grid' => 'Organization grid',
        'grid_label' => 'Organizations near you',
    ],

    'stat' => [
        'title' => 'Stat',
        'description' => 'A large number with its label, on a flat block. Numbers are formatted in the current language.',
        'a11y_semantics' => 'Number and label are in the same paragraph, so they are read together; several stats go in a list.',
        'a11y_icon' => 'The icon is decorative: the meaning is in the label.',
        'bento' => 'Bento row',
        'bento_label' => 'ArkVel in numbers',
        'adopted' => 'animals adopted',
        'organizations' => 'organizations in the network',
        'rating' => 'average rating from families',
        'success' => 'successful adoptions',
        'regions' => 'regions covered',
        'volunteers' => 'active volunteers',
        'tones' => 'Tones',
    ],

    'section_heading' => [
        'title' => 'Section heading',
        'description' => 'Optional eyebrow, title, description and an action on the right on desktop and below on mobile. The title has an id for the section’s aria-labelledby.',
        'a11y_level' => 'Heading level (heading-level) and appearance (size) are independent.',
        'a11y_labelledby' => 'The <section> around it uses aria-labelledby with the title’s id.',
        'with_action' => 'With eyebrow and action',
        'text_eyebrow' => 'Text eyebrow, no action',
        'demo_eyebrow' => 'New arrivals',
        'demo_title' => 'Waiting for a home',
        'demo_description' => 'The latest animals to arrive at the shelters in the network: dogs, cats and a few surprises.',
        'demo_eyebrow_text' => 'How it works',
        'demo_title_small' => 'Four steps to adopt',
    ],

    'cta_band' => [
        'title' => 'CTA band',
        'description' => 'Full-width panel with a title, text and actions, stacking on mobile. The green version with the yellow CTA is the donation band.',
        'a11y_tone' => 'Text uses the on-* color of the background; button focus takes the same color.',
        'a11y_yellow' => 'Yellow on green is only the button background: as text below 24 px it does not reach 4.5:1.',
        'donation' => 'Donation band (leaf)',
        'donation_title' => 'Help us find a home for everyone',
        'donation_text' => '€10 covers a week of care for an animal in a shelter.',
        'donate' => 'Donate now',
        'shelter' => 'For organizations (inverse)',
        'shelter_title' => 'Do you run a shelter or an association?',
        'shelter_text' => 'Publish your animals, receive applications and organize visits in one place.',
        'shelter_action' => 'Register your organization',
        'tones' => 'Other tones',
        'tone_title' => ':tone tone',
        'tone_names' => ['sun' => 'Sun', 'peach' => 'Peach', 'mint' => 'Mint'],
        'tone_text' => 'On these backgrounds the button is primary.',
        'tone_action' => 'Learn more',
    ],
];
