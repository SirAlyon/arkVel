<?php

declare(strict_types=1);

return [
    // Notifica ai membri dell'ente quando viene richiesto un nuovo appuntamento.
    'new_requested' => [
        'subject' => 'Nuovo appuntamento richiesto per :animal',
        'greeting' => 'Ciao,',
        'line' => ':requester ha richiesto un appuntamento (:type) per :animal il :when.',
        'action' => 'Gestisci gli appuntamenti',
        'salutation' => 'A presto, il team di ArkVel',
        // Messaggio breve per la notifica in-app (canale database).
        'database' => ':requester ha richiesto un appuntamento (:type) per :animal.',
    ],

    // Notifica al richiedente quando l'ente conferma l'appuntamento.
    'confirmed' => [
        'subject' => 'Appuntamento confermato per :animal',
        'greeting' => 'Ciao :name,',
        'line' => 'Il tuo appuntamento per :animal è stato confermato per il :when.',
        'action' => 'Vai al mio account',
        'salutation' => 'A presto, il team di ArkVel',
        // Messaggio breve per la notifica in-app (canale database).
        'database' => 'Il tuo appuntamento per :animal è confermato per il :when.',
    ],

    // Notifica alla controparte quando un appuntamento viene annullato.
    'cancelled' => [
        'subject' => 'Appuntamento annullato per :animal',
        'greeting' => 'Ciao,',
        'line' => 'L\'appuntamento per :animal del :when è stato annullato.',
        'reason' => 'Motivazione: :reason',
        'action' => 'Vai al mio account',
        'salutation' => 'A presto, il team di ArkVel',
        // Messaggio breve per la notifica in-app (canale database).
        'database' => 'L\'appuntamento per :animal del :when è stato annullato.',
    ],

    // Notifica al richiedente quando l'appuntamento è completato.
    'completed' => [
        'subject' => 'Appuntamento completato per :animal',
        'greeting' => 'Ciao :name,',
        'line' => 'Il tuo appuntamento per :animal è stato completato. Grazie!',
        'action' => 'Vai al mio account',
        'salutation' => 'A presto, il team di ArkVel',
        // Messaggio breve per la notifica in-app (canale database).
        'database' => 'Il tuo appuntamento per :animal è stato completato.',
    ],

    // Notifica al richiedente quando viene registrata la sua assenza.
    'no_show' => [
        'subject' => 'Assenza registrata per l\'appuntamento su :animal',
        'greeting' => 'Ciao :name,',
        'line' => 'Risulti assente all\'appuntamento per :animal del :when.',
        'action' => 'Vai al mio account',
        'salutation' => 'A presto, il team di ArkVel',
        // Messaggio breve per la notifica in-app (canale database).
        'database' => 'Risulti assente all\'appuntamento per :animal.',
    ],

    // Promemoria al richiedente per un appuntamento confermato imminente.
    'reminder' => [
        'subject' => 'Promemoria: appuntamento per :animal',
        'greeting' => 'Ciao :name,',
        'line' => 'Ti ricordiamo l\'appuntamento (:type) per :animal del :when.',
        'action' => 'Vai al mio account',
        'salutation' => 'A presto, il team di ArkVel',
        // Messaggio breve per la notifica in-app (canale database).
        'database' => 'Promemoria: appuntamento (:type) per :animal del :when.',
        // Riepilogo stampato dal comando arkvel:send-appointment-reminders.
        'command_summary' => 'Promemoria appuntamenti inviati: :count.',
    ],

    // Widget "Prossimi appuntamenti" della dashboard shelter.
    'widget' => [
        'heading' => 'Prossimi appuntamenti',
        'description' => 'Appuntamenti attivi ancora da tenersi, in ordine cronologico.',
        'scheduled_at' => 'Data e ora',
        'animal' => 'Animale',
        'requester' => 'Richiedente',
        'type' => 'Tipo',
        'status' => 'Stato',
        'empty_title' => 'Nessun appuntamento in arrivo',
        'empty_body' => 'Non ci sono appuntamenti attivi in programma.',
    ],

    // CTA di prenotazione sulla scheda pubblica dell'animale.
    'booking' => [
        'cta_title' => 'Prenota una visita',
        'cta_body' => 'Vuoi conoscere :name di persona? Scegli tipo e orario e prenota un incontro con l\'ente.',
        'type' => 'Tipo di incontro',
        'scheduled_at' => 'Data e ora',
        'notes' => 'Note (facoltative)',
        'notes_placeholder' => 'Aggiungi un\'informazione utile per l\'ente…',
        'submit' => 'Prenota',
        'submitting' => 'Prenotazione in corso…',
        'my_appointments' => 'I miei appuntamenti',
        'flash_success' => 'Appuntamento richiesto: l\'ente ti confermerà l\'orario.',
        // Messaggi di validazione / errori di dominio mostrati in-place.
        'validation' => [
            'type_required' => 'Scegli il tipo di incontro.',
            'type_invalid' => 'Il tipo di incontro selezionato non è valido.',
            'scheduled_at_required' => 'Indica data e ora dell\'appuntamento.',
            'scheduled_at_invalid' => 'La data e l\'ora indicate non sono valide.',
        ],
        'slot_in_past' => 'Scegli una data e un\'ora nel futuro.',
        'slot_unavailable' => 'Quello slot non è disponibile: prova con un altro orario.',
    ],

    // Pagina "I miei appuntamenti" del pannello adottante.
    'my' => [
        'title' => 'I miei appuntamenti',
        'animal' => 'Animale',
        'organization' => 'Ente',
        'type' => 'Tipo',
        'status' => 'Stato',
        'scheduled_at' => 'Data e ora',
        'cancel' => 'Annulla',
        'cancel_confirm_heading' => 'Annullare l\'appuntamento?',
        'cancel_confirm_body' => 'L\'appuntamento verrà annullato e l\'ente ne sarà avvisato. Vuoi procedere?',
        'cancel_confirm_submit' => 'Annulla l\'appuntamento',
        'cancel_done' => 'Appuntamento annullato.',
        'cancel_failed' => 'Impossibile annullare l\'appuntamento',
        'empty_title' => 'Nessun appuntamento',
        'empty_body' => 'Non hai ancora prenotato appuntamenti. Sfoglia il catalogo e prenota una visita per l\'animale che ti interessa.',
    ],

    // Gestione appuntamenti lato ente (pannello shelter).
    'shelter' => [
        'nav_group' => 'Appuntamenti',
        'label' => 'Appuntamento',
        'plural_label' => 'Appuntamenti',
        'nav_label' => 'Appuntamenti',

        // Colonne / campi.
        'scheduled_at' => 'Data e ora',
        'animal' => 'Animale',
        'requester' => 'Richiedente',
        'email' => 'Email',
        'type' => 'Tipo',
        'status' => 'Stato',
        'organization' => 'Ente',
        'notes' => 'Note',

        // Filtri della tabella.
        'filter_status' => 'Stato',
        'filter_type' => 'Tipo',
        'filter_from' => 'Dal',
        'filter_to' => 'Al',
        'filter_upcoming' => 'Solo futuri',

        // Tab della board (per fase del ciclo di vita).
        'tabs' => [
            'all' => 'Tutti',
            'to_confirm' => 'Da confermare',
            'confirmed' => 'Confermati',
            'closed' => 'Conclusi',
        ],

        // Sezioni della view / infolist.
        'section_appointment' => 'Appuntamento',
        'section_requester' => 'Richiedente',

        // Azioni di gestione staff.
        'confirm' => 'Conferma',
        'confirm_heading' => 'Confermare l\'appuntamento?',
        'confirm_body' => 'Il richiedente riceverà la conferma dell\'orario.',
        'confirm_submit' => 'Conferma',
        'confirm_done' => 'Appuntamento confermato.',
        'confirm_failed' => 'Impossibile confermare l\'appuntamento',

        'complete' => 'Completa',
        'complete_heading' => 'Segnare l\'appuntamento come completato?',
        'complete_body' => 'L\'incontro risulterà avvenuto.',
        'complete_submit' => 'Completa',
        'complete_done' => 'Appuntamento completato.',
        'complete_failed' => 'Impossibile completare l\'appuntamento',

        'no_show' => 'Assenza',
        'no_show_heading' => 'Registrare l\'assenza del richiedente?',
        'no_show_body' => 'L\'appuntamento risulterà non onorato.',
        'no_show_submit' => 'Registra assenza',
        'no_show_done' => 'Assenza registrata.',
        'no_show_failed' => 'Impossibile registrare l\'assenza',

        'cancel' => 'Annulla',
        'cancel_heading' => 'Annullare l\'appuntamento?',
        'cancel_submit' => 'Annulla l\'appuntamento',
        'cancel_reason' => 'Motivazione (facoltativa)',
        'cancel_reason_help' => 'Comunicata al richiedente nell\'avviso di annullamento.',
        'cancel_done' => 'Appuntamento annullato.',
        'cancel_failed' => 'Impossibile annullare l\'appuntamento',

        'empty_title' => 'Nessun appuntamento',
        'empty_body' => 'Quando qualcuno prenota una visita per uno dei tuoi animali, l\'appuntamento comparirà qui.',
    ],
];
