<?php

declare(strict_types=1);

return [
    // Notifica alla controparte quando arriva un nuovo messaggio in una
    // conversazione (stessa classe per adottante e staff dell'ente).
    'new_message' => [
        'subject' => 'Nuovo messaggio da :sender',
        'greeting' => 'Ciao,',
        'line' => ':sender ti ha inviato un nuovo messaggio:',
        'action' => 'Leggi il messaggio',
        'salutation' => 'A presto, il team di ArkVel',
        // Messaggio breve per la notifica in-app (canale database).
        'database' => ':sender ti ha inviato un nuovo messaggio.',
    ],

    // Stringhe condivise del thread di conversazione (lista + modale + risposta),
    // riusate dal pannello adottante e dal pannello ente.
    'thread' => [
        'organization' => 'Ente',
        'adopter' => 'Adottante',
        'animal' => 'Animale',
        'subject' => 'Oggetto',
        'last_message' => 'Ultimo messaggio',
        'last_message_at' => 'Aggiornata',
        'unread' => 'Non letti',
        'no_subject' => 'Senza oggetto',
        'no_messages' => 'Nessun messaggio.',
        'open' => 'Apri',
        'open_heading' => 'Conversazione',
        'close' => 'Chiudi',
        'you' => 'Tu',
        'sent_at' => 'Inviato il :date',
    ],

    // Box di risposta a una conversazione esistente (delegato a PostMessage).
    'reply' => [
        'placeholder' => 'Scrivi un messaggio…',
        'label' => 'Messaggio',
        'send' => 'Invia',
        'sending' => 'Invio…',
        'sent' => 'Messaggio inviato.',
        'empty' => 'Il messaggio non può essere vuoto.',
    ],

    // Pannello adottante: pagina "Messaggi".
    'adopter' => [
        'title' => 'Messaggi',
        'nav_label' => 'Messaggi',
        'empty_title' => 'Nessuna conversazione',
        'empty_body' => 'Quando contatti un ente, le conversazioni compaiono qui.',
    ],

    // Pannello ente: pagina "Conversazioni" (tenant-scoped).
    'shelter' => [
        'title' => 'Conversazioni',
        'nav_label' => 'Conversazioni',
        'nav_group' => 'Comunicazione',
        'empty_title' => 'Nessuna conversazione',
        'empty_body' => 'Le conversazioni con gli adottanti compaiono qui.',
        // Avvio di una nuova conversazione da parte dello staff.
        'new' => 'Nuova conversazione',
        'new_heading' => 'Scrivi a un adottante',
        'new_submit' => 'Invia',
        'new_adopter' => 'Adottante',
        'new_adopter_help' => 'Solo le persone già in relazione con il tuo ente.',
        'new_subject' => 'Oggetto (facoltativo)',
        'new_body' => 'Messaggio',
        'new_done' => 'Conversazione avviata.',
        'new_failed' => 'Impossibile avviare la conversazione.',
    ],

    // Sito pubblico: CTA "Contatta l'ente" dalla scheda animale.
    'contact' => [
        'cta_title' => 'Contatta l\'ente',
        'cta_body' => 'Hai domande su :name? Scrivi direttamente all\'ente che se ne prende cura.',
        'placeholder' => 'Scrivi il tuo messaggio…',
        'submit' => 'Invia messaggio',
        'submitting' => 'Invio…',
        'my_messages' => 'I miei messaggi',
        'flash_success' => 'Messaggio inviato all\'ente. Troverai la risposta nei tuoi messaggi.',
        'flash_appended' => 'Messaggio aggiunto alla conversazione esistente.',
        'empty' => 'Scrivi un messaggio prima di inviare.',
    ],
];
