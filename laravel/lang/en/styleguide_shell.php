<?php

declare(strict_types=1);

/*
 | Styleguide · "shell" group (resources/views/styleguide/groups/shell.blade.php).
 */

return [
    'nav_link' => [
        'title' => 'Navigation item',
        'description' => 'Header "pill" link and full-width row of the mobile menu. The active item is solid navy; wire:navigate.hover prefetches the page on hover or focus.',
        'a11y_current' => 'The active state is not just a colour: aria-current="page" on the item’s page, aria-current="true" inside its section (e.g. an animal page for “Catalog”).',
        'a11y_target' => '44 px targets (pill) and 48 px (mobile menu row).',
        'a11y_navigate' => 'Towards the Filament panels (another layout) SPA navigation is turned off with :navigate="false".',
        'pill' => 'Header pill, on the current page',
        'section' => 'Pill inside the current section',
        'block' => 'Mobile menu row, with an icon',
    ],

    'language_switcher' => [
        'title' => 'Language switcher',
        'description' => 'Segmented control: one button per language, saving the choice in the session with a POST (works without JavaScript too).',
        'a11y_pressed' => 'The active language has aria-pressed="true"; the group is named “Language”.',
        'a11y_lang' => 'Each option has the lang attribute: screen readers pronounce the endonym in the right language.',
        'a11y_compact' => 'In the compact version the visible code (IT, EN) starts the accessible name, followed by the endonym.',
        'full' => 'With the endonym (mobile menu, footer)',
        'compact' => 'Compact (header)',
    ],

    'header' => [
        'title' => 'Header',
        'description' => 'Logo, pill navigation, language and account; below md the navigation moves into the mobile menu (drawer). Sticky, with a border appearing on scroll; it can be transparent over the hero (transparentHeader). Shown here as a static preview, guest variant.',
        'a11y_logo' => 'The logo is the home link: named “arkvel, home page”, starting with the visible wordmark.',
        'a11y_menu' => 'The mobile menu is a modal dialog: focus is trapped, Esc closes it and focus goes back to the button.',
        'a11y_account' => 'The account menu shows the first name and, for screen readers, its purpose (“Your account”).',
        'guest' => 'Guest (narrow the window for the mobile menu)',
    ],

    'footer' => [
        'title' => 'Footer',
        'description' => 'Logo and tagline, main and account links, newsletter (placeholder, not active yet), upcoming legal pages, language and copyright. Guest variant.',
        'a11y_newsletter' => 'The newsletter form is disabled and says so in words: no field that looks like it works and does not.',
        'a11y_legal' => 'The legal pages do not exist yet: text labelled “Coming soon”, no broken links.',
        'guest' => 'Guest',
    ],
];
