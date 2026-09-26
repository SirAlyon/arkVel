<?php

declare(strict_types=1);

return [
    // Notifica all'adottante quando lo stato della candidatura cambia.
    'status_changed' => [
        'subject' => 'Aggiornamento della tua candidatura per :animal',
        'greeting' => 'Ciao :name,',
        'line' => 'Lo stato della tua candidatura per :animal è passato a ":status".',
        'action' => 'Vedi la candidatura',
        'salutation' => 'A presto, il team di ArkVel',
        // Messaggio breve per la notifica in-app (canale database).
        'database' => 'La tua candidatura per :animal è ora ":status".',
    ],

    // Notifica ai membri dell'ente quando arriva una nuova candidatura.
    'new_received' => [
        'subject' => 'Nuova candidatura per :animal',
        'greeting' => 'Ciao,',
        'line' => ':adopter ha inviato una candidatura di adozione per :animal.',
        'action' => 'Gestisci le candidature',
        'salutation' => 'A presto, il team di ArkVel',
        // Messaggio breve per la notifica in-app (canale database).
        'database' => ':adopter si è candidato per :animal.',
    ],

    // Notifica ai membri dell'ente quando un adottante ritira la candidatura.
    'withdrawn' => [
        'subject' => 'Candidatura ritirata per :animal',
        'greeting' => 'Ciao,',
        'line' => ':adopter ha ritirato la candidatura di adozione per :animal.',
        'action' => 'Gestisci le candidature',
        'salutation' => 'A presto, il team di ArkVel',
        // Messaggio breve per la notifica in-app (canale database).
        'database' => ':adopter ha ritirato la candidatura per :animal.',
    ],

    // Flusso di candidatura dalla scheda pubblica dell'animale.
    'apply' => [
        'submitting' => 'Invio in corso…',
        'applied' => 'Candidatura inviata',
        'my_applications' => 'Le mie candidature',
        'flash_success' => 'Candidatura inviata: l\'ente ti ricontatterà.',
        'flash_duplicate' => 'Hai già inviato una candidatura per questo animale.',
        'complete_profile' => 'Completa il tuo profilo adottante per poterti candidare.',
    ],

    // Gestione candidature lato ente (pannello shelter).
    'shelter' => [
        'nav_group' => 'Adozioni',
        'label' => 'Candidatura',
        'plural_label' => 'Candidature',
        'nav_label' => 'Candidature',

        // Colonne / campi.
        'animal' => 'Animale',
        'adopter' => 'Adottante',
        'status' => 'Stato',
        'assigned_to' => 'In carico a',
        'unassigned' => 'Non assegnata',
        'submitted_at' => 'Inviata il',
        'decided_at' => 'Decisa il',
        'decision_reason' => 'Motivazione della decisione',
        'staff_notes' => 'Note interne',

        // Tab della board (per stato).
        'tabs' => [
            'all' => 'Tutte',
            'to_review' => 'Da valutare',
            'in_progress' => 'In corso',
            'decided' => 'Decise',
        ],

        // Azioni di riga.
        'advance' => 'Cambia stato',
        'advance_heading' => 'Aggiorna lo stato della candidatura',
        'advance_submit' => 'Applica',
        'new_status' => 'Nuovo stato',
        'reason' => 'Motivazione (facoltativa)',
        'reason_help' => 'Comunicata all\'adottante nelle decisioni (approvata / respinta / lista d\'attesa).',
        'advanced' => 'Stato aggiornato',
        'advanced_body' => 'La candidatura è ora ":status".',
        'advance_failed' => 'Transizione non consentita',
        'assign' => 'Assegna',
        'assign_heading' => 'Assegna la candidatura a un membro',
        'assign_submit' => 'Assegna',
        'assign_member' => 'Membro incaricato',
        'assign_done' => 'Candidatura assegnata.',
        'notes' => 'Note interne',
        'notes_heading' => 'Modifica le note interne',
        'notes_submit' => 'Salva',
        'notes_done' => 'Note aggiornate.',

        // Sezioni della view / infolist.
        'section_application' => 'Candidatura',
        'section_adopter' => 'Profilo adottante',
        'section_adopter_help' => 'Dati minimizzati a quanto serve per la decisione e per organizzare il colloquio (GDPR).',
        'section_contact' => 'Contatti',
        'section_notes' => 'Gestione interna',

        // Campi del profilo adottante.
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
            'hours_alone_weekday' => 'Ore da solo (feriali)',
            'hours_alone_weekend' => 'Ore da solo (weekend)',
            'lifestyle' => 'Stile di vita',
            'ideal_animal' => 'Animale ideale',
            'contingency_plan' => 'Piano in caso di imprevisti',
            'previous_surrender' => 'Rinunce precedenti',
            'phone' => 'Telefono',
            'city' => 'Città',
            'region' => 'Regione',
            'country' => 'Paese',
            'consent' => 'Consenso al trattamento dati',
            'consented_at' => 'Consenso prestato il',
            'no_profile' => 'L\'adottante non ha ancora compilato il profilo.',
        ],

        'yes' => 'Sì',
        'no' => 'No',
        'unknown' => 'Non indicato',

        'empty_title' => 'Nessuna candidatura',
        'empty_body' => 'Quando un adottante si candida per uno dei tuoi animali, la candidatura comparirà qui.',
    ],

    // Pagina "Le mie candidature" del pannello adottante.
    'my' => [
        'title' => 'Le mie candidature',
        'animal' => 'Animale',
        'organization' => 'Ente',
        'status' => 'Stato',
        'submitted_at' => 'Inviata il',
        'decision_reason' => 'Motivazione',
        'withdraw' => 'Ritira',
        'withdraw_confirm_heading' => 'Ritirare la candidatura?',
        'withdraw_confirm_body' => 'La candidatura verrà ritirata e non potrà più essere riattivata. Vuoi procedere?',
        'withdraw_confirm_submit' => 'Ritira la candidatura',
        'withdraw_done' => 'Candidatura ritirata.',
        'empty_title' => 'Nessuna candidatura',
        'empty_body' => 'Non hai ancora inviato candidature. Sfoglia il catalogo e candidati per l\'animale che ti interessa.',
    ],
];
