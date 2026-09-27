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
        'empty_body' => 'Collega un calendario esterno per far comparire lì gli appuntamenti dell\'ente. Puoi collegare CalDAV (Proton, Fastmail, Nextcloud, iCloud) o Google Calendar (se configurato dall\'amministratore).',

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

        // Flusso Google Calendar (Cal-5).
        'google' => [
            'name' => 'Google Calendar',
            'not_configured' => 'Per abilitare Google Calendar imposta le variabili GOOGLE_CALENDAR_* nella configurazione del server.',
            'connected' => 'Google Calendar collegato: gli appuntamenti verranno sincronizzati.',
            'error' => 'Impossibile completare il collegamento a Google Calendar. Riprova.',
        ],

        // Flusso CalDAV (Cal-6): Proton (via Bridge), Fastmail, Nextcloud, iCloud.
        'caldav' => [
            'name' => 'CalDAV',
            'heading' => 'Collega un calendario CalDAV',
            'body' => 'Inserisci l\'indirizzo del calendario (collezione CalDAV) e le credenziali. Funziona con Proton (tramite Bridge), Fastmail, Nextcloud, iCloud e ogni server CalDAV standard. Usa una password per app, non la password principale.',
            'submit' => 'Collega',
            'url' => 'Indirizzo del calendario (URL della collezione)',
            'url_help' => 'Es. https://caldav.fastmail.com/dav/calendars/user/nome@dominio/calendario/',
            'username' => 'Nome utente',
            'username_help' => 'L\'indirizzo email o il nome utente dell\'account CalDAV.',
            'app_password' => 'Password per app',
            'app_password_help' => 'Genera una password dedicata dal tuo provider. Viene salvata cifrata e non è più visibile dopo il salvataggio.',
            'verify_failed' => 'Impossibile raggiungere il calendario con questi dati. Verifica indirizzo, nome utente e password per app.',
            'connected' => 'Calendario CalDAV collegato: gli appuntamenti verranno sincronizzati.',
        ],

        // Azione: disconnetti.
        'disconnect' => 'Disconnetti',
        'disconnect_heading' => 'Disconnettere il calendario?',
        'disconnect_body' => 'La connessione verrà rimossa e gli appuntamenti non saranno più sincronizzati verso questo calendario. Gli eventi già presenti nel calendario esterno non verranno cancellati.',
        'disconnect_submit' => 'Disconnetti',
        'disconnect_done' => 'Calendario disconnesso.',
    ],

    // Riconciliazione pull (Cal-7): modifiche dai calendari esterni riportate in ArkVel.
    'reconcile' => [
        'cancelled_externally' => 'Appuntamento annullato dal calendario esterno.',
        'command_summary' => 'Sync calendari: :connections connessioni, :rescheduled riprogrammati, :cancelled annullati.',
    ],
];
