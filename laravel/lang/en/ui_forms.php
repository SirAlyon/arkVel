<?php

declare(strict_types=1);

/*
 | Testi dei componenti x-ui del gruppo "forms" (resources/views/components/ui):
 | field, input, textarea, select, checkbox, radio, switch e gruppi.
 */

return [
    'field' => [
        'required' => '(required)',
        'optional' => '(optional)',
        'error' => 'Error:',
    ],

    'input' => [
        'show_password' => 'Show password',
    ],

    'textarea' => [
        'limit' => 'Up to :max characters.',
        'remaining' => ':count characters left',
        'remaining_one' => '1 character left',
        'limit_reached' => 'You have reached the :max character limit',
    ],

    'select' => [
        'placeholder' => 'Choose an option',
    ],

    'error_summary' => [
        'title' => 'There is a problem to fix|There are :count problems to fix',
    ],
];
