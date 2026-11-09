<?php

declare(strict_types=1);

/*
 | Styleguide · gruppo "pickers" (resources/views/styleguide/groups/pickers.blade.php).
 */

return [

    'combobox' => [
        'title' => 'Combobox',
        'description' => 'Pill field with a list of options filtered as you type. For choosing among many items (breeds, shelters, towns); for a few options the native select is enough.',
        'a11y_pattern' => 'APG pattern “combobox with listbox popup”: role="combobox", aria-expanded, aria-controls, aria-autocomplete="list" and aria-activedescendant. Focus always stays in the field.',
        'a11y_keyboard' => '↓ / ↑ open and move through the options, Home / End go to the first / last one, Enter chooses, Esc closes (a second Esc clears), Tab closes and confirms.',
        'a11y_state' => 'The chosen option has aria-selected and a check icon; the active one has the focus ring: state is never conveyed by color alone.',
        'a11y_live' => '“No results” is written in the list; the number of results reaches screen readers through a polite live region, once you stop typing.',
        'basic' => 'In a field',
        'breed' => 'Breed',
        'breed_help' => 'Type a few letters: the search ignores case and accents.',
        'breed_placeholder' => 'Search for a breed',
        'described' => 'With description and initial value',
        'shelter' => 'Shelter',
        'model' => 'Bound value (x-model / wire:model)',
        'species' => 'Species',
        'current' => 'Value:',
        'empty_value' => 'none',
        'error_title' => 'Required, with an error',
        'error' => 'Choose a breed from the list.',
        'disabled' => 'Disabled',
        'standalone' => 'Standalone, with an icon, on a surface',
        'standalone_label' => 'Search for a town',
        'server' => 'Server-side search (Livewire)',
        'server_text' => 'With search="propertyName" the component does not filter by itself: it writes the text into the Livewire property (after 300 ms) and shows the options returned by the server.',
    ],

    'date_picker' => [
        'title' => 'Date picker',
        'description' => 'Wrapper of Cally (MIT web component): a pill field with the date written in full and a calendar in a panel, or always visible. Cally is downloaded only on the pages that use it.',
        'a11y_dialog' => 'The “Open the calendar” button has aria-haspopup="dialog" and aria-expanded; the panel is a named dialog. When it opens, focus moves to the chosen day (or today).',
        'a11y_keyboard' => 'In the grid: arrows (day and week), Home / End (start and end of the week), Page Up / Page Down (month), Shift+Page Up / Page Down (year), Enter or Space to choose. Esc closes and focus returns to the button.',
        'a11y_days' => 'Every day is named with its full date. Today has a ring and aria-current, unavailable days are struck through and have aria-disabled: never color alone.',
        'a11y_live' => 'After choosing, a live region announces “Selected date: …”; in the inline variant the chosen date is written below the calendar.',
        'popover' => 'Popover in a field',
        'visit' => 'Visit date',
        'visit_help' => 'You can choose from today onwards.',
        'disallowed' => 'Unavailable days and a clear button',
        'pickup' => 'Pick-up date',
        'pickup_help' => 'The shelter is closed on Sundays and on the marked holidays.',
        'model' => 'Bound value (x-model / wire:model)',
        'birth' => 'Date of birth (estimated)',
        'current' => 'ISO value:',
        'empty_value' => 'none',
        'error_title' => 'Required, with an error',
        'error' => 'Choose the date of the visit.',
        'inline' => 'Inline (booking)',
        'inline_label' => 'Day of the visit to the shelter',
        'disabled' => 'Disabled',
    ],

    'breeds' => [
        'labrador' => 'Labrador',
        'golden' => 'Golden retriever',
        'german_shepherd' => 'German shepherd',
        'border_collie' => 'Border collie',
        'beagle' => 'Beagle',
        'dachshund' => 'Dachshund',
        'jack_russell' => 'Jack Russell',
        'poodle' => 'Poodle',
        'irish_setter' => 'Irish setter',
        'epagneul' => 'Épagneul breton',
        'maremma' => 'Maremma sheepdog',
        'mixed' => 'Mixed breed',
    ],

    'shelters' => [
        'faro' => 'Il Faro shelter',
        'faro_city' => 'Genoa · dogs and cats',
        'querce' => 'Le Querce kennel',
        'querce_city' => 'Bologna · dogs',
        'gattopardo' => 'Gattopardo cat sanctuary',
        'gattopardo_city' => 'Palermo · cats',
        'coda' => 'Coda Felice association',
        'coda_city' => 'Turin · dogs, cats and rabbits',
    ],

    'species' => [
        'dog' => 'Dog',
        'cat' => 'Cat',
        'rabbit' => 'Rabbit',
        'ferret' => 'Ferret',
        'guinea_pig' => 'Guinea pig',
    ],

    'towns' => [
        'milano' => 'Milan',
        'monza' => 'Monza',
        'modena' => 'Modena',
        'macerata' => 'Macerata',
        'forli' => 'Forlì',
        'cefalu' => 'Cefalù',
    ],
];
