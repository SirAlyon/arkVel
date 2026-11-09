<?php

declare(strict_types=1);

/*
 | Testi dei componenti x-ui del gruppo "forms" (resources/views/components/ui):
 | field, input, textarea, select, checkbox, radio, switch e gruppi.
 */

return [
    'field' => [
        'required' => '(obbligatorio)',
        'optional' => '(facoltativo)',
        'error' => 'Errore:',
    ],

    'input' => [
        'show_password' => 'Mostra password',
    ],

    'textarea' => [
        'limit' => 'Massimo :max caratteri.',
        'remaining' => 'Restano :count caratteri',
        'remaining_one' => 'Resta 1 carattere',
        'limit_reached' => 'Hai raggiunto il limite di :max caratteri',
    ],

    'select' => [
        'placeholder' => 'Scegli un’opzione',
    ],

    'error_summary' => [
        'title' => 'C’è un problema da correggere|Ci sono :count problemi da correggere',
    ],
];
