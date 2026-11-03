<?php

declare(strict_types=1);

return [
    // Navigazione e titolo della pagina "Notifiche" (centro notifiche: storico
    // completo, oltre alla campanella). Usata nei pannelli adottante ed ente.
    'nav_label' => 'Notifiche',
    'title' => 'Notifiche',

    // Azioni sulle notifiche.
    'mark_read' => 'Segna come letta',
    'mark_all_read' => 'Segna tutte come lette',
    'open' => 'Apri',
    'marked_read' => 'Notifica segnata come letta.',
    'all_marked_read' => 'Tutte le notifiche sono state segnate come lette.',

    // Stato di lettura (badge/colonna).
    'state' => [
        'unread' => 'Non letta',
        'read' => 'Letta',
    ],

    // Filtro "solo non lette".
    'filter' => [
        'unread_only' => 'Solo non lette',
    ],

    // Fallback quando la notifica non ha un messaggio leggibile nel payload.
    'fallback_message' => 'Hai ricevuto una notifica.',

    // Colonne della tabella.
    'column' => [
        'message' => 'Messaggio',
        'received_at' => 'Ricevuta',
        'state' => 'Stato',
    ],

    // Stato vuoto.
    'empty_title' => 'Nessuna notifica',
    'empty_body' => 'Qui vedrai le notifiche su candidature, affidi, appuntamenti e messaggi.',

    // Tagline del footer delle email transazionali (brand ArkVel).
    'email_footer_tagline' => 'ArkVel — piattaforma per l\'adozione e la gestione animali.',
];
