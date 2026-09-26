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

    // Flusso di candidatura dalla scheda pubblica dell'animale.
    'apply' => [
        'submitting' => 'Invio in corso…',
        'applied' => 'Candidatura inviata',
        'my_applications' => 'Le mie candidature',
        'flash_success' => 'Candidatura inviata: l\'ente ti ricontatterà.',
        'flash_duplicate' => 'Hai già inviato una candidatura per questo animale.',
        'complete_profile' => 'Completa il tuo profilo adottante per poterti candidare.',
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
