<?php

declare(strict_types=1);

return [
    // Sito pubblico: sezione "Sostieni l'ente" sulla pagina dell'ente + landing di
    // ritorno dal Checkout (grazie / annulla).
    'public' => [
        'heading' => 'Sostieni :name',
        'subheading' => 'La tua donazione aiuta l\'ente a prendersi cura degli animali. Il pagamento è gestito in sicurezza da Stripe.',
        'frequency_label' => 'Con quale frequenza?',
        'frequency_one_off' => 'Una tantum',
        'frequency_monthly' => 'Mensile',
        'monthly_requires_login' => 'Accedi per donare mensilmente e gestire la donazione dal tuo account.',
        'amount_label' => 'Scegli un importo',
        'custom_amount_label' => 'Oppure inserisci un importo',
        'name_label' => 'Il tuo nome (facoltativo)',
        'email_label' => 'La tua email',
        'message_label' => 'Messaggio (facoltativo)',
        'submit' => 'Dona ora',
        'submitting' => 'Reindirizzamento…',
        'secure_note' => 'Pagamento sicuro con carta o addebito SEPA tramite Stripe.',
        'not_configured' => 'Le donazioni non sono al momento disponibili per questo ente.',

        'validation' => [
            'amount_required' => 'Indica un importo da donare.',
            'amount_numeric' => 'L\'importo deve essere un numero.',
            'amount_min' => 'L\'importo minimo è 1 €.',
            'amount_max' => 'L\'importo massimo è 10.000 €.',
            'email_required' => 'Inserisci la tua email per ricevere la ricevuta.',
            'email_invalid' => 'Inserisci un indirizzo email valido.',
        ],

        // Landing di ringraziamento (success_url).
        'thanks_title' => 'Grazie per la tua donazione',
        'thanks_body' => 'Il tuo sostegno a :name fa la differenza per gli animali.',
        'thanks_pending_note' => 'La conferma del pagamento può richiedere qualche istante. Riceverai una ricevuta via email.',
        'back_to_organization' => 'Torna alla pagina dell\'ente',

        // Landing di annullamento (cancel_url).
        'cancel_title' => 'Donazione annullata',
        'cancel_body' => 'Nessun pagamento è stato effettuato. Puoi riprovare quando vuoi.',
        'cancel_retry' => 'Riprova a donare',
    ],

    // Pannello ente: registro donazioni (tenant-scoped, sola lettura).
    'shelter' => [
        'nav_group' => 'Raccolta fondi',
        'nav_label' => 'Donazioni',
        'label' => 'Donazione',
        'plural_label' => 'Donazioni',
        'date' => 'Data',
        'donor' => 'Donatore',
        'anonymous' => 'Anonimo',
        'amount' => 'Importo',
        'type' => 'Tipo',
        'status' => 'Stato',
        'message' => 'Messaggio',
        'filter_status' => 'Stato',
        'empty_title' => 'Nessuna donazione',
        'empty_body' => 'Le donazioni ricevute dal tuo ente compaiono qui.',

        'tabs' => [
            'all' => 'Tutte',
            'completed' => 'Completate',
            'pending' => 'In attesa',
            'unsuccessful' => 'Non riuscite',
        ],
    ],

    // Pannello adottante: le mie donazioni ricorrenti + storico addebiti (ownership).
    'my' => [
        'title' => 'Le mie donazioni',
        'organization' => 'Ente',
        'amount' => 'Importo',
        'amount_monthly' => ':amount € / mese',
        'status' => 'Stato',
        'started_at' => 'Attiva dal',
        'canceled_at' => 'Disdetta il',
        'cancel' => 'Disdici',
        'cancel_confirm_heading' => 'Disdire la donazione mensile?',
        'cancel_confirm_body' => 'Non ti verrà più addebitato nulla. La donazione ricorrente verrà disdetta. Vuoi procedere?',
        'cancel_confirm_submit' => 'Disdici la donazione',
        'cancel_done' => 'Donazione ricorrente disdetta.',
        'empty_title' => 'Nessuna donazione ricorrente',
        'empty_body' => 'Non hai donazioni mensili attive. Dalla pagina di un ente puoi avviare una donazione ricorrente.',
        'charges_heading' => 'Storico addebiti',
        'charges_description' => 'Gli addebiti mensili andati a buon fine delle tue donazioni ricorrenti.',
        'charge_date' => 'Data',
    ],
];
