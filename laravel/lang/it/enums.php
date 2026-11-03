<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Etichette degli enum applicativi (App\Enums)
|--------------------------------------------------------------------------
|
| Una chiave per enum (snake_case del nome della classe), una per case (il
| backing value). Eccezioni:
|  - permission_name: il value `<risorsa>.<azione>` diventa un percorso annidato;
|  - role_name: il value contiene spazi, si usa il nome del case in snake_case.
| AppLocale non è qui: usa endonimi indipendenti dalla lingua corrente.
*/

return [
    'animal_status' => [
        'draft' => 'Bozza',
        'available' => 'Disponibile',
        'reserved' => 'Prenotato',
        'adoption_pending' => 'Adozione in corso',
        'adopted' => 'Adottato',
        'fostered' => 'In affido',
        'medical_hold' => 'Fermo sanitario',
        'not_available' => 'Non disponibile',
    ],

    'application_status' => [
        'submitted' => 'Inviata',
        'under_review' => 'In valutazione',
        'interview' => 'Colloquio / verifica',
        'approved' => 'Approvata',
        'adoption_pending' => 'Adozione in corso',
        'completed' => 'Completata',
        'rejected' => 'Respinta',
        'withdrawn' => 'Ritirata',
        'waitlisted' => 'Lista d\'attesa',
    ],

    'appointment_status' => [
        'requested' => 'Richiesto',
        'confirmed' => 'Confermato',
        'completed' => 'Completato',
        'cancelled' => 'Annullato',
        'no_show' => 'Assente',
    ],

    'appointment_type' => [
        'visit' => 'Visita',
        'interview' => 'Colloquio',
        'home_check' => 'Controllo domiciliare',
    ],

    'calendar_connection_status' => [
        'active' => 'Attiva',
        'error' => 'Errore',
        'disconnected' => 'Disconnessa',
    ],

    'calendar_provider' => [
        'google' => 'Google Calendar',
        'caldav' => 'CalDAV',
        'log' => 'Demo (solo log)',
    ],

    'calendar_sync_direction' => [
        'push' => 'Solo invio (ArkVel → esterno)',
        'both' => 'Due vie (invio e ricezione)',
    ],

    'donation_status' => [
        'pending' => 'In attesa',
        'completed' => 'Completata',
        'failed' => 'Fallita',
        'refunded' => 'Rimborsata',
        'canceled' => 'Annullata',
    ],

    'donation_type' => [
        'one_off' => 'Una tantum',
        'recurring' => 'Ricorrente',
    ],

    'energy_level' => [
        'low' => 'Basso',
        'medium' => 'Medio',
        'high' => 'Alto',
    ],

    'follow_up_status' => [
        'pending' => 'Da pianificare',
        'scheduled' => 'Pianificato',
        'completed' => 'Completato',
        'not_needed' => 'Non necessario',
    ],

    'foster_application_status' => [
        'submitted' => 'Inviata',
        'under_review' => 'In valutazione',
        'approved' => 'Approvata',
        'rejected' => 'Respinta',
        'waitlisted' => 'Lista d\'attesa',
        'active' => 'Affido in corso',
        'completed' => 'Completato',
        'withdrawn' => 'Ritirata',
    ],

    'foster_placement_status' => [
        'active' => 'In corso',
        'ended' => 'Concluso',
    ],

    'foster_placement_type' => [
        'pre_adoption' => 'Pre-adozione',
        'medical' => 'Sanitario',
        'emergency' => 'Emergenza',
        'long_term' => 'Lungo termine',
    ],

    'housing_type' => [
        'apartment' => 'Appartamento',
        'house' => 'Casa indipendente',
        'farm' => 'Casa rurale / con terreno',
        'other' => 'Altro',
    ],

    'medical_record_type' => [
        'vaccination' => 'Vaccinazione',
        'treatment' => 'Trattamento',
        'surgery' => 'Intervento chirurgico',
        'checkup' => 'Visita di controllo',
        'deworming' => 'Sverminazione',
    ],

    'membership_role' => [
        'owner' => 'Titolare',
        'staff' => 'Staff',
        'volunteer' => 'Volontario',
    ],

    'organization_status' => [
        'pending' => 'In attesa',
        'verified' => 'Verificato',
        'suspended' => 'Sospeso',
    ],

    'organization_type' => [
        'canile_comunale' => 'Canile comunale',
        'canile_privato' => 'Canile privato',
        'rifugio' => 'Rifugio',
        'associazione' => 'Associazione',
        'societa' => 'Società',
    ],

    'permission_name' => [
        'organization' => [
            'update' => 'Modifica ente',
            'manage_staff' => 'Gestisci staff ente',
        ],
        'animal' => [
            'view' => 'Visualizza animali',
            'create' => 'Crea animali',
            'update' => 'Modifica animali',
            'delete' => 'Elimina animali',
        ],
        'medical_record' => [
            'view' => 'Visualizza cartelle sanitarie',
            'create' => 'Crea cartelle sanitarie',
            'update' => 'Modifica cartelle sanitarie',
            'delete' => 'Elimina cartelle sanitarie',
        ],
        'application' => [
            'view' => 'Visualizza candidature',
            'manage' => 'Gestisci candidature',
        ],
        'foster_application' => [
            'view' => 'Visualizza richieste di affido',
            'manage' => 'Gestisci richieste di affido',
        ],
        'appointment' => [
            'view' => 'Visualizza appuntamenti',
            'manage' => 'Gestisci appuntamenti',
        ],
        'calendar' => [
            'manage' => 'Gestisci sincronizzazione calendario',
        ],
        'message' => [
            'view' => 'Visualizza messaggi',
            'manage' => 'Gestisci messaggi',
        ],
        'donation' => [
            'view' => 'Visualizza donazioni',
            'manage' => 'Gestisci donazioni',
        ],
    ],

    'recurring_donation_status' => [
        'pending' => 'In attesa',
        'active' => 'Attiva',
        'past_due' => 'Insoluto',
        'canceled' => 'Disdetta',
    ],

    'rent_or_own' => [
        'own' => 'Di proprietà',
        'rent' => 'In affitto',
    ],

    'role_name' => [
        'super_admin' => 'Super Amministratore',
        'org_owner' => 'Titolare ente',
        'org_staff' => 'Staff ente',
        'org_volunteer' => 'Volontario ente',
        'adopter' => 'Adottante',
    ],

    'sex' => [
        'male' => 'Maschio',
        'female' => 'Femmina',
    ],

    'size' => [
        'small' => 'Piccola',
        'medium' => 'Media',
        'large' => 'Grande',
    ],

    'species' => [
        'dog' => 'Cane',
        'cat' => 'Gatto',
        'other' => 'Altro',
    ],
];
