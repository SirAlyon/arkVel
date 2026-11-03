<?php

declare(strict_types=1);

return [
    // Pagina super-admin (pannello Admin): credenziali dell'app OAuth Google Calendar
    // a livello di piattaforma. Ogni ente collega poi il proprio account Google.
    'nav_group' => 'Impostazioni',
    'nav' => 'Integrazione calendario',
    'title' => 'Integrazione Google Calendar',

    // Sezione del form.
    'section_google' => 'App OAuth Google Calendar',
    'section_google_help' => 'Configura qui le credenziali dell\'app OAuth di Google Calendar per l\'intera piattaforma. I valori impostati qui hanno la precedenza sulle variabili d\'ambiente (.env). Ogni ente collegherà poi il proprio account Google dal pannello dell\'ente.',

    // Campi.
    'client_id' => 'Client ID',
    'client_id_help' => 'Il Client ID OAuth 2.0 creato nella Google Cloud Console.',
    'client_secret' => 'Client secret',
    'client_secret_placeholder' => 'Lascia vuoto per mantenere il segreto attuale',
    'client_secret_stored' => 'Un client secret è attualmente configurato. Compila il campo solo per sostituirlo; lasciandolo vuoto resterà invariato.',
    'client_secret_missing' => 'Nessun client secret configurato. Inseriscilo per abilitare l\'integrazione.',
    'redirect_uri' => 'Redirect URI',
    'redirect_uri_help' => 'Registra questo esatto URI di reindirizzamento nella Google Cloud Console (Credenziali → URI di reindirizzamento autorizzati): :url. Se lasci il campo vuoto, verrà usato automaticamente questo indirizzo.',

    // Stato della configurazione.
    'status_db' => 'Configurato dall\'interfaccia (i valori salvati qui hanno la precedenza sull\'.env).',
    'status_env' => 'Configurato tramite variabili d\'ambiente (.env). Puoi sovrascriverle qui.',
    'status_none' => 'Google Calendar non è ancora configurato: inserisci Client ID e Client secret.',

    // Azioni ed esiti.
    'save' => 'Salva',
    'saved' => 'Impostazioni dell\'integrazione salvate.',
];
