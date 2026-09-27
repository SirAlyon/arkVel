<?php

declare(strict_types=1);

return [
    // Pagina di gestione delle connessioni a calendari esterni (pannello ente).
    'connections' => [
        'nav_group' => 'Appuntamenti',
        'nav' => 'Sincronizzazione calendario',
        'title' => 'Sincronizzazione calendario',
        'subheading' => 'Collega i calendari esterni dell\'ente: gli appuntamenti vi compariranno e resteranno aggiornati automaticamente.',

        // Colonne della tabella.
        'provider' => 'Provider',
        'name' => 'Nome',
        'status' => 'Stato',
        'direction' => 'Direzione',
        'last_synced_at' => 'Ultima sincronizzazione',
        'last_error' => 'Ultimo errore',
        'never_synced' => 'Mai',

        // Stato vuoto.
        'empty_title' => 'Nessun calendario collegato',
        'empty_body' => 'Collega un calendario esterno per far comparire lì gli appuntamenti dell\'ente. In questa fase è disponibile la connessione dimostrativa; Google e CalDAV arriveranno a breve.',

        // Azione: connetti (demo, provider di log).
        'connect_demo' => 'Connetti (demo)',
        'connect_demo_heading' => 'Collegare un calendario dimostrativo?',
        'connect_demo_body' => 'Crea una connessione dimostrativa (senza credenziali): gli appuntamenti verranno "sincronizzati" verso un provider che registra soltanto le operazioni. Serve a provare la pipeline prima dell\'integrazione con Google e CalDAV.',
        'connect_demo_submit' => 'Connetti',
        'connect_demo_name' => 'Calendario dimostrativo',
        'connect_demo_done' => 'Connessione dimostrativa creata: gli appuntamenti verranno sincronizzati.',

        // Azioni: connessioni reali.
        'connect_google' => 'Connetti Google Calendar',
        'connect_caldav' => 'Connetti CalDAV',
        'coming_soon' => 'Disponibile a breve',
        'coming_soon_notice' => 'La connessione con CalDAV sarà disponibile in un aggiornamento successivo.',

        // Flusso Google Calendar (Cal-5).
        'google' => [
            'name' => 'Google Calendar',
            'not_configured' => 'Per abilitare Google Calendar imposta le variabili GOOGLE_CALENDAR_* nella configurazione del server.',
            'connected' => 'Google Calendar collegato: gli appuntamenti verranno sincronizzati.',
            'error' => 'Impossibile completare il collegamento a Google Calendar. Riprova.',
        ],

        // Azione: disconnetti.
        'disconnect' => 'Disconnetti',
        'disconnect_heading' => 'Disconnettere il calendario?',
        'disconnect_body' => 'La connessione verrà rimossa e gli appuntamenti non saranno più sincronizzati verso questo calendario. Gli eventi già presenti nel calendario esterno non verranno cancellati.',
        'disconnect_submit' => 'Disconnetti',
        'disconnect_done' => 'Calendario disconnesso.',
    ],
];
