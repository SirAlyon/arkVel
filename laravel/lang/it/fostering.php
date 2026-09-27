<?php

declare(strict_types=1);

return [
    // Notifica ai membri dell'ente quando arriva una nuova richiesta di affido.
    'new_received' => [
        'subject' => 'Nuova richiesta di affido per :animal',
        'greeting' => 'Ciao,',
        'line' => ':carer ha inviato una richiesta di affido per :animal.',
        'action' => 'Gestisci gli affidi',
        'salutation' => 'A presto, il team di ArkVel',
        // Messaggio breve per la notifica in-app (canale database).
        'database' => ':carer ha chiesto l\'affido di :animal.',
    ],

    // Notifica all'affidatario quando lo stato della richiesta cambia.
    'status_changed' => [
        'subject' => 'Aggiornamento della tua richiesta di affido per :animal',
        'greeting' => 'Ciao :name,',
        'line' => 'Lo stato della tua richiesta di affido per :animal è passato a ":status".',
        'action' => 'Vedi la richiesta',
        'salutation' => 'A presto, il team di ArkVel',
        // Messaggio breve per la notifica in-app (canale database).
        'database' => 'La tua richiesta di affido per :animal è ora ":status".',
    ],

    // Notifica ai membri dell'ente quando un affidatario ritira la richiesta.
    'withdrawn' => [
        'subject' => 'Richiesta di affido ritirata per :animal',
        'greeting' => 'Ciao,',
        'line' => ':carer ha ritirato la richiesta di affido per :animal.',
        'action' => 'Gestisci gli affidi',
        'salutation' => 'A presto, il team di ArkVel',
        // Messaggio breve per la notifica in-app (canale database).
        'database' => ':carer ha ritirato la richiesta di affido per :animal.',
    ],

    // Notifica all'affidatario quando l'affido inizia.
    'placement_started' => [
        'subject' => 'Affido avviato: :animal è con te',
        'greeting' => 'Ciao :name,',
        'line' => 'L\'affido di :animal è iniziato: grazie per l\'accoglienza!',
        'action' => 'Vai al mio account',
        'salutation' => 'A presto, il team di ArkVel',
        // Messaggio breve per la notifica in-app (canale database).
        'database' => 'L\'affido di :animal è iniziato.',
    ],

    // Notifica all'affidatario quando l'affido si conclude.
    'placement_ended' => [
        'subject' => 'Affido concluso: :animal',
        'greeting' => 'Ciao :name,',
        'line' => 'L\'affido di :animal si è concluso. Grazie per il tuo aiuto!',
        'action' => 'Vai al mio account',
        'salutation' => 'A presto, il team di ArkVel',
        // Messaggio breve per la notifica in-app (canale database).
        'database' => 'L\'affido di :animal si è concluso.',
    ],

    // CTA "Richiedi affido" dalla scheda pubblica dell'animale.
    'request' => [
        'cta_body' => 'Non puoi adottare ma vuoi aiutare? Offriti come affidatario temporaneo.',
        'cta_button' => 'Richiedi l\'affido',
        'submitting' => 'Invio in corso…',
        'requested' => 'Richiesta inviata',
        'my_foster_applications' => 'I miei affidi',
        'flash_success' => 'Richiesta di affido inviata: l\'ente ti ricontatterà.',
        'flash_duplicate' => 'Hai già inviato una richiesta di affido per questo animale.',
    ],

    // Pagina "I miei affidi" del pannello adottante.
    'my' => [
        'title' => 'I miei affidi',
        'animal' => 'Animale',
        'organization' => 'Ente',
        'status' => 'Stato',
        'submitted_at' => 'Inviata il',
        'decision_reason' => 'Motivazione',
        'placement_type' => 'Tipo di affido',
        'placement_start' => 'Inizio affido',
        'placement_end' => 'Fine affido',
        'withdraw' => 'Ritira',
        'withdraw_confirm_heading' => 'Ritirare la richiesta di affido?',
        'withdraw_confirm_body' => 'La richiesta verrà ritirata. Se l\'affido è in corso, l\'animale rientrerà all\'ente. Vuoi procedere?',
        'withdraw_confirm_submit' => 'Ritira la richiesta',
        'withdraw_done' => 'Richiesta di affido ritirata.',
        'empty_title' => 'Nessun affido',
        'empty_body' => 'Non hai ancora inviato richieste di affido. Sfoglia il catalogo e offriti come affidatario per l\'animale che ti interessa.',
    ],

    // Gestione richieste di affido lato ente (pannello shelter).
    'shelter' => [
        'nav_group' => 'Affidi',
        'label' => 'Richiesta di affido',
        'plural_label' => 'Richieste di affido',
        'nav_label' => 'Affidi',

        // Colonne / campi.
        'animal' => 'Animale',
        'species' => 'Specie',
        'carer' => 'Affidatario',
        'email' => 'Email',
        'status' => 'Stato',
        'assigned_to' => 'In carico a',
        'unassigned' => 'Non assegnata',
        'submitted_at' => 'Inviata il',
        'decided_at' => 'Decisa il',
        'decision_reason' => 'Motivazione della decisione',
        'staff_notes' => 'Note interne',
        'placement_type' => 'Tipo di affido',
        'placement_status' => 'Stato affido',
        'placement_start' => 'Inizio affido',
        'placement_end' => 'Fine affido',
        'placement_end_help' => 'Lascia vuoto se l\'affido non ha una scadenza prestabilita.',
        'placement_notes' => 'Note sull\'affido',

        // Tab della board (per stato).
        'tabs' => [
            'all' => 'Tutte',
            'to_review' => 'Da valutare',
            'in_progress' => 'In corso',
            'decided' => 'Decise',
        ],

        // Azioni di riga.
        'advance' => 'Cambia stato',
        'advance_heading' => 'Aggiorna lo stato della richiesta',
        'advance_submit' => 'Applica',
        'new_status' => 'Nuovo stato',
        'reason' => 'Motivazione (facoltativa)',
        'reason_help' => 'Comunicata all\'affidatario nelle decisioni (approvata / respinta / lista d\'attesa).',
        'advanced' => 'Stato aggiornato',
        'advanced_body' => 'La richiesta è ora ":status".',
        'advance_failed' => 'Transizione non consentita',

        'start_placement' => 'Avvia affido',
        'start_placement_heading' => 'Avvia l\'affido fisico',
        'start_placement_help' => 'L\'animale verrà collocato presso l\'affidatario e passerà "In affido".',
        'start_placement_submit' => 'Avvia',
        'start_placement_failed' => 'Impossibile avviare l\'affido',
        'placement_started' => 'Affido avviato',
        'placement_started_body' => ':animal è ora in affido.',

        'end_placement' => 'Concludi affido',
        'end_placement_heading' => 'Concludi l\'affido fisico',
        'end_placement_help' => 'L\'affido verrà chiuso e l\'animale tornerà disponibile.',
        'end_placement_submit' => 'Concludi',
        'end_placement_failed' => 'Impossibile concludere l\'affido',
        'placement_ended' => 'Affido concluso',
        'placement_ended_body' => 'L\'affido di :animal si è concluso.',
        'no_active_placement' => 'Non c\'è un affido in corso da concludere.',

        'assign' => 'Assegna',
        'assign_heading' => 'Assegna la richiesta a un membro',
        'assign_submit' => 'Assegna',
        'assign_member' => 'Membro incaricato',
        'assign_done' => 'Richiesta assegnata.',
        'notes' => 'Note interne',
        'notes_heading' => 'Modifica le note interne',
        'notes_submit' => 'Salva',
        'notes_done' => 'Note aggiornate.',

        // Sezioni della view / infolist.
        'section_application' => 'Richiesta di affido',
        'section_placement' => 'Affido fisico',
        'section_carer' => 'Profilo affidatario',
        'section_carer_help' => 'Dati minimizzati a quanto serve per la decisione e per organizzare l\'affido (GDPR).',
        'section_contact' => 'Contatti',
        'section_notes' => 'Gestione interna',

        // Campi del profilo affidatario (riusa lo screening dell\'adozione).
        'profile' => [
            'housing_type' => 'Tipo di abitazione',
            'has_garden' => 'Giardino',
            'fenced' => 'Recintato',
            'rent_or_own' => 'Proprietà / affitto',
            'landlord_allows_pets' => 'Il proprietario consente animali',
            'adults_in_home' => 'Adulti in casa',
            'kids_in_home' => 'Bambini in casa',
            'other_pets' => 'Altri animali',
            'experience' => 'Esperienza',
            'contingency_plan' => 'Piano in caso di imprevisti',
            'phone' => 'Telefono',
            'city' => 'Città',
            'region' => 'Regione',
            'country' => 'Paese',
            'consent' => 'Consenso al trattamento dati',
            'consented_at' => 'Consenso prestato il',
        ],

        'yes' => 'Sì',
        'no' => 'No',
        'unknown' => 'Non indicato',

        'empty_title' => 'Nessuna richiesta di affido',
        'empty_body' => 'Quando un affidatario si offre per uno dei tuoi animali, la richiesta comparirà qui.',
    ],
];
