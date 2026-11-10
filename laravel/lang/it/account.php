<?php

declare(strict_types=1);

return [
    // Pannello adottante: "danger zone" di eliminazione account (GDPR art. 17,
    // anonimizzazione in-place). Chiavi usate dalla pagina DeleteAccount,
    // dall'Action AnonymizeUserAccount e dall'eccezione CannotAnonymizeSoleOwner.
    'delete' => [
        'nav' => 'Elimina account',
        'title' => 'Elimina il mio account',

        // Riquadro esplicativo della pagina.
        'zone_heading' => 'Elimina definitivamente il tuo account',
        'zone_intro' => 'Eliminando il tuo account eserciti il diritto all\'oblio: i tuoi dati personali vengono rimossi o resi anonimi e l\'accesso viene bloccato.',
        'zone_removed_profile' => 'Il tuo questionario adottante e i relativi dati vengono eliminati.',
        'zone_removed_favorites' => 'I tuoi animali preferiti e le notifiche vengono rimossi.',
        'zone_removed_login' => 'Nome ed email vengono resi anonimi e non potrai più accedere.',
        'zone_removed_recurring' => 'Le donazioni ricorrenti attive vengono disdette.',
        'zone_kept' => 'Per obblighi di legge e per la storia dell\'ente, candidature, affidi, appuntamenti, messaggi e le donazioni già effettuate restano conservati in forma anonima (senza i tuoi dati personali).',
        'zone_irreversible' => 'Questa operazione è irreversibile e non può essere annullata.',

        // Azione e modale di conferma.
        'button' => 'Elimina il mio account',
        'confirm_heading' => 'Confermi l\'eliminazione dell\'account?',
        'confirm_body' => 'L\'operazione è irreversibile. Per confermare, digita qui sotto il tuo indirizzo email.',
        'confirm_submit' => 'Elimina definitivamente',
        'confirm_email_label' => 'Conferma la tua email',
        'confirm_email_help' => 'Digita l\'indirizzo email con cui accedi per confermare.',
        'confirm_email_mismatch' => 'L\'email non corrisponde a quella del tuo account.',

        // Esito.
        'done_flash' => 'Il tuo account è stato eliminato. Grazie di aver fatto parte di ArkVel.',
        'sole_owner_title' => 'Impossibile eliminare l\'account',
        'sole_owner_body' => 'Sei l\'unico titolare di un ente. Prima di eliminare l\'account, trasferisci la titolarità a un altro membro o chiudi l\'ente.',

        // Valori sostitutivi scritti al posto della PII.
        'name_placeholder' => 'Account eliminato',
        'donor_placeholder' => 'Donatore anonimo',

        // Messaggio dell'eccezione di dominio (guardia unico-owner).
        'sole_owner_exception' => 'Non puoi anonimizzare l\'account: sei l\'unico titolare dell\'ente #:organization.',
    ],
];
