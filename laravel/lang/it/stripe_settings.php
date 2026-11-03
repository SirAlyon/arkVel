<?php

declare(strict_types=1);

return [
    // Pagina super-admin (pannello Admin): credenziali Stripe a livello di piattaforma.
    // Un solo account Stripe per l'intera istanza; le donazioni sono registrate per-ente.
    'nav_group' => 'Impostazioni',
    'nav' => 'Integrazione pagamenti',
    'title' => 'Integrazione Stripe',

    // Sezione del form.
    'section' => 'Credenziali Stripe',
    'section_help' => 'Configura qui le chiavi del tuo account Stripe per l\'intera piattaforma. I valori impostati qui hanno la precedenza sulle variabili d\'ambiente (.env). Finché non sono configurate publishable key e secret key, le donazioni restano disattivate sul sito pubblico.',

    // Campi.
    'publishable_key' => 'Publishable key',
    'publishable_key_help' => 'La chiave pubblicabile (pk_...) del tuo account Stripe. Non è un segreto.',
    'secret_key' => 'Secret key',
    'secret_placeholder' => 'Lascia vuoto per mantenere il valore attuale',
    'secret_stored' => 'Una secret key è attualmente configurata. Compila il campo solo per sostituirla; lasciandolo vuoto resterà invariata.',
    'secret_missing' => 'Nessuna secret key configurata. Inseriscila (sk_...) per abilitare le donazioni.',
    'webhook_secret' => 'Webhook signing secret',
    'webhook_stored' => 'Un webhook secret è attualmente configurato. Compila il campo solo per sostituirlo; lasciandolo vuoto resterà invariato.',
    'webhook_missing' => 'Nessun webhook secret configurato. Crea un endpoint webhook su Stripe che punti a :url e incolla qui il signing secret (whsec_...). Senza, le conferme di pagamento non vengono verificate.',

    // Stato della configurazione.
    'status_db' => 'Configurato dall\'interfaccia (i valori salvati qui hanno la precedenza sull\'.env).',
    'status_env' => 'Configurato tramite variabili d\'ambiente (.env). Puoi sovrascriverle qui.',
    'status_none' => 'Stripe non è ancora configurato: inserisci publishable key e secret key per abilitare le donazioni.',

    // Azioni ed esiti.
    'save' => 'Salva',
    'saved' => 'Credenziali Stripe salvate.',
];
